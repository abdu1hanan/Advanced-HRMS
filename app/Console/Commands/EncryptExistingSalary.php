<?php

namespace App\Console\Commands;

use App\Services\SalaryEncrypter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncryptExistingSalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:encrypt-existing
                            {--chunk=200 : Number of records to process per chunk}
                            {--dry-run : Simulate encryption without persisting changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt all existing plaintext compensation values (expected_salary_amount and sales_commission) in the users table';

    /**
     * Execute the console command.
     */
    public function handle(SalaryEncrypter $salaryEncrypter): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] Running in simulation mode. No changes will be written to the database.');
        }

        $this->info('Verifying salary encryption configuration...');

        try {
            // Confirm encrypter is ready by generating test ciphertext
            $testCipher = $salaryEncrypter->encrypt('test');
            $salaryEncrypter->decrypt($testCipher);
        } catch (Throwable $e) {
            $this->error('Salary encryption service failed initialization: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $totalUsers = DB::table('users')->count();
        $this->info("Scanning {$totalUsers} user records in chunks of {$chunkSize}...");

        $stats = [
            'users_scanned'               => 0,
            'users_updated'               => 0,
            'salary_encrypted'            => 0,
            'salary_already_encrypted'    => 0,
            'salary_null_or_empty'        => 0,
            'commission_encrypted'        => 0,
            'commission_already_encrypted'=> 0,
            'commission_null_or_empty'    => 0,
            'errors'                      => 0,
        ];

        DB::table('users')
            ->select(['id', 'expected_salary_amount', 'sales_commission'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (&$stats, $salaryEncrypter, $dryRun) {
                foreach ($users as $user) {
                    $stats['users_scanned']++;
                    $updates = [];

                    // 1. Process expected_salary_amount
                    $rawSalary = $user->expected_salary_amount;
                    if ($rawSalary === null || $rawSalary === '') {
                        $stats['salary_null_or_empty']++;
                    } elseif ($salaryEncrypter->isEncrypted($rawSalary)) {
                        $stats['salary_already_encrypted']++;
                    } elseif ($salaryEncrypter->isLegacyPlaintext($rawSalary)) {
                        $normalized = (string) (int) round((float) $rawSalary);
                        $updates['expected_salary_amount'] = $salaryEncrypter->encrypt($normalized);
                        $stats['salary_encrypted']++;
                    } else {
                        $this->error("User ID #{$user->id}: Unrecognized format in expected_salary_amount. Skipping field to protect data.");
                        $stats['errors']++;
                    }

                    // 2. Process sales_commission
                    $rawCommission = $user->sales_commission;
                    if ($rawCommission === null || $rawCommission === '') {
                        $stats['commission_null_or_empty']++;
                    } elseif ($salaryEncrypter->isEncrypted($rawCommission)) {
                        $stats['commission_already_encrypted']++;
                    } elseif ($salaryEncrypter->isLegacyPlaintext($rawCommission)) {
                        $normalized = (string) (int) round((float) $rawCommission);
                        $updates['sales_commission'] = $salaryEncrypter->encrypt($normalized);
                        $stats['commission_encrypted']++;
                    } else {
                        $this->error("User ID #{$user->id}: Unrecognized format in sales_commission. Skipping field to protect data.");
                        $stats['errors']++;
                    }

                    // 3. Persist raw updates directly to DB without triggering Eloquent casts
                    if (!empty($updates)) {
                        if (!$dryRun) {
                            DB::table('users')->where('id', $user->id)->update($updates);
                        }
                        $stats['users_updated']++;
                    }
                }
            });

        $this->newLine();
        $this->info('Compensation encryption process complete.');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users Scanned', $stats['users_scanned']],
                ['Users With Changes', $stats['users_updated']],
                ['Expected Salary: Newly Encrypted', $stats['salary_encrypted']],
                ['Expected Salary: Already Encrypted', $stats['salary_already_encrypted']],
                ['Expected Salary: Null/Empty', $stats['salary_null_or_empty']],
                ['Sales Commission: Newly Encrypted', $stats['commission_encrypted']],
                ['Sales Commission: Already Encrypted', $stats['commission_already_encrypted']],
                ['Sales Commission: Null/Empty', $stats['commission_null_or_empty']],
                ['Unrecognized/Errors', $stats['errors']],
            ]
        );

        if ($stats['errors'] > 0) {
            $this->warn("Completed with {$stats['errors']} error(s). Please review any warnings above.");
            return Command::FAILURE;
        }

        $this->info('All compensation data successfully processed and verified.');
        return Command::SUCCESS;
    }
}
