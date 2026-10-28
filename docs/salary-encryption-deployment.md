# Production Deployment Guide: Transparent Compensation Encryption

This guide details the exact operational procedure for deploying transparent compensation encryption (`users.expected_salary_amount` and `users.sales_commission`) in the BeetleOps HRMS application.

---

> [!CAUTION]
> **PERMANENT KEY LOSS WARNING**
>
> If `encrypt_key` is permanently lost, deleted, or corrupted, all encrypted employee compensation data (`expected_salary_amount` and `sales_commission`) **CANNOT BE RECOVERED**. There is no backdoor, master key, or brute-force recovery mechanism.
>
> **You MUST securely store a backup of `encrypt_key` in your organization's secure password manager or cloud key vault (e.g., 1Password, AWS Secrets Manager, HashiCorp Vault) BEFORE deploying.**

---

## Architecture & Cryptographic Primitives

- **Algorithm**: `AES-256-CBC` with authenticated MAC (HMAC-SHA256 Encrypt-then-MAC) using Laravel's native `Illuminate\Encryption\Encrypter`.
- **Key Isolation**: The encryption key is sourced strictly from `encrypt_key` (`services.salary.encrypt_key`), completely independent of Laravel's `APP_KEY`. Rotating `APP_KEY` does not affect salary encryption, and repository access alone does not reveal salaries.
- **Model Transparency**: `User::$expected_salary_amount` and `User::$sales_commission` cast via `App\Casts\EncryptedSalary`. Application logic, payroll estimates, attendance deductions, and salary slips interact with normal integer values transparently.
- **Migration Backward Compatibility**: The cast supports legacy unencrypted numeric records during the deployment window. Malformed or tampered ciphertext is never treated as plaintext and fails closed.

---

## Step-by-Step Production Deployment Sequence

Follow these steps in exact chronological order:

### Step 1: Generate a Secure 256-bit Encryption Key

On a secure workstation or the production server, generate a 256-bit key using the built-in Artisan command:

```bash
php artisan salary:generate-key
```

*Alternatively via command line:*
```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Output format will resemble:
```text
base64:ckw4RWPogpRKDpZWn/kZF46pgQvhmngsJy/iVCfwPSY=
```

---

### Step 2: Set `encrypt_key` in the Production Environment

Add the generated key to your production environment configuration (e.g. `.env` or container environment variables):

```env
encrypt_key=base64:<your-generated-key-here>
```

> [!NOTE]
> Do NOT wrap the key in quotes if it contains `=` padding characters. Ensure there are no leading or trailing whitespace characters.

---

### Step 3: Securely Back Up the Key

Immediately copy the key to your team's secure secrets vault (e.g. 1Password Vault, AWS Secrets Manager, or Google Secret Manager). Verify that at least two authorized system administrators have access to this backup.

---

### Step 4: Deploy the Code

Deploy the updated codebase containing:
- `config/services.php` (with salary configuration)
- `app/Services/SalaryEncrypter.php`
- `app/Casts/EncryptedSalary.php`
- `app/Models/User.php`
- `app/Exceptions/SalaryEncryptionException.php`
- `database/migrations/2026_09_17_170000_change_compensation_columns_to_text_in_users_table.php`
- `app/Console/Commands/EncryptExistingSalary.php`
- `app/Console/Commands/DecryptExistingSalary.php`

After code deployment, refresh the configuration cache:

```bash
php artisan config:cache
```

---

### Step 5: Run the Database Migration

Migrate the compensation columns from integer types to `TEXT`:

```bash
php artisan migrate --force
```

This updates `users.expected_salary_amount` and `users.sales_commission` to nullable `TEXT` fields in MySQL without modifying existing plaintext data.

---

### Step 6: Confirm Application Starts and Validates Configuration

Confirm the application is healthy and `SalaryEncrypter` successfully loads the key:

```bash
php artisan salary:encrypt-existing --dry-run
```

You should see:
```text
[DRY RUN] Running in simulation mode. No changes will be written to the database.
Verifying salary encryption configuration...
Scanning X user records in chunks of 200...
```

If the key is missing or malformed, the command will halt immediately and report an informative error.

---

### Step 7: Run the Compensation Encryption Command

Encrypt all existing plaintext compensation values across the database:

```bash
php artisan salary:encrypt-existing
```

For large databases, you can customize chunk size (default: 200):
```bash
php artisan salary:encrypt-existing --chunk=500
```

---

### Step 8: Confirm Command Completion

Verify the summary table output:
- **Users With Changes**: Number of rows modified
- **Newly Encrypted**: Count of salaries & commissions encrypted
- **Already Encrypted**: 0 (on first run)
- **Errors**: 0

*Note: This command is fully idempotent. If interrupted, it can be re-run safely without double-encrypting already processed records.*

---

### Step 9: Verify Raw Database Values are Ciphertext

Query the database directly using MySQL CLI or Laravel Tinker to verify that raw database values contain encrypted base64 JSON payload:

```bash
php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$sample = DB::table("users")->whereNotNull("expected_salary_amount")->select("id", "expected_salary_amount", "sales_commission")->first();
echo "Raw DB Stored Salary: " . substr($sample->expected_salary_amount, 0, 30) . "...\n";
'
```

Output should show a string starting with base64 ciphertext (e.g. `eyJpdiI6...`), confirming **NO plaintext numbers exist in the database**.

---

### Step 10: Verify Application Salary Screens & Calculations

Log into the HRMS admin panel and verify:
1. **User Profile / Edit (`super-admin/users/{id}/edit`)**:
   - Expected Salary and Sales Commission display formatted PKR values.
   - Saving a modified salary stores new ciphertext in the database.
2. **Salary Estimation (`super-admin/salary/estimations`)**:
   - Monthly base salaries, attendance deductions, commissions, and net pay calculate accurately.
3. **Salary Slips & PDF Generation (`super-admin/salary-slips`)**:
   - Slips render correctly with base salary and commission.
   - PDF export and email previews generate accurate numbers.
4. **Excel Exports**:
   - Exported spreadsheet values match the expected compensation amounts.

---

### Step 11: Confirm No Plaintext Compensation Remains

Run this check to confirm zero plaintext compensation records remain in the database:

```bash
php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$enc = app(App\Services\SalaryEncrypter::class);
$unencrypted = DB::table("users")
    ->select("id", "expected_salary_amount", "sales_commission")
    ->get()
    ->filter(fn($u) => ($u->expected_salary_amount !== null && !$enc->isEncrypted($u->expected_salary_amount))
                    || ($u->sales_commission !== null && !$enc->isEncrypted($u->sales_commission)));
echo "Unencrypted rows remaining: " . $unencrypted->count() . "\n";
'
```

Expected output: `Unencrypted rows remaining: 0`.

---

## Emergency Rollback & Recovery Procedure

If you must revert the release:

1. **Decrypt Database Values Back to Plaintext**:
   The migration `down()` method contains safety checks preventing rollback while ciphertext exists. You must decrypt data first:
   ```bash
   php artisan salary:decrypt-existing --force
   ```
2. **Roll Back Database Schema**:
   ```bash
   php artisan migrate:rollback --step=1
   ```
3. **Revert Application Code** and clear config cache:
   ```bash
   php artisan config:cache
   ```
