<?php

namespace App\Console\Commands;

use App\Services\SalaryEncrypter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DecryptExistingSalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:decrypt-existing
                            {--chunk=200 : Number of records to process per chunk}
                            {--dry-run : Simulate decryption without persisting changes to the database}
                            {--force : Force execution without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypt all encrypted compensation values back into plaintext numbers (for rollback/recovery)';

    /**
     * Execute the console command.
     */
    public function handle(SalaryEncrypter $salaryEncrypter): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!$force && !$dryRun) {
            $confirmed = $this->confirm(
                'This command will decrypt all compensation values back to plaintext numbers in the database. Are you sure you want to proceed?',
                false
            );

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] Running in simulation mode. No changes will be written to the database.');
        }

        $totalUsers = DB::table('users')->count();
        $this->info("Scanning {$totalUsers} user records in chunks of {$chunkSize} for decryption...");

        $stats = [
            'users_scanned'               => 0,
            'users_updated'               => 0,
            'salary_decrypted'            => 0,
            'salary_already_plaintext'    => 0,
            'salary_null_or_empty'        => 0,
            'commission_decrypted'        => 0,
            'commission_already_plaintext'=> 0,
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
                        try {
                            $decrypted = $salaryEncrypter->decrypt($rawSalary);
                            $updates['expected_salary_amount'] = (int) round((float) $decrypted);
                            $stats['salary_decrypted']++;
                        } catch (Throwable $e) {
                            $this->error("User ID #{$user->id}: Decryption failed for expected_salary_amount. Skipping.");
                            $stats['errors']++;
                        }
                    } elseif ($salaryEncrypter->isLegacyPlaintext($rawSalary)) {
                        $stats['salary_already_plaintext']++;
                    } else {
                        $this->error("User ID #{$user->id}: Unrecognized format for expected_salary_amount. Skipping.");
                        $stats['errors']++;
                    }

                    // 2. Process sales_commission
                    $rawCommission = $user->sales_commission;
                    if ($rawCommission === null || $rawCommission === '') {
                        $stats['commission_null_or_empty']++;
                    } elseif ($salaryEncrypter->isEncrypted($rawCommission)) {
                        try {
                            $decrypted = $salaryEncrypter->decrypt($rawCommission);
                            $updates['sales_commission'] = (int) round((float) $decrypted);
                            $stats['commission_decrypted']++;
                        } catch (Throwable $e) {
                            $this->error("User ID #{$user->id}: Decryption failed for sales_commission. Skipping.");
                            $stats['errors']++;
                        }
                    } elseif ($salaryEncrypter->isLegacyPlaintext($rawCommission)) {
                        $stats['commission_already_plaintext']++;
                    } else {
                        $this->error("User ID #{$user->id}: Unrecognized format for sales_commission. Skipping.");
                        $stats['errors']++;
                    }

                    // 3. Persist raw updates
                    if (!empty($updates)) {
                        if (!$dryRun) {
                            DB::table('users')->where('id', $user->id)->update($updates);
                        }
                        $stats['users_updated']++;
                    }
                }
            });

        $this->newLine();
        $this->info('Compensation decryption process complete.');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users Scanned', $stats['users_scanned']],
                ['Users With Changes', $stats['users_updated']],
                ['Expected Salary: Decrypted to Plaintext', $stats['salary_decrypted']],
                ['Expected Salary: Already Plaintext', $stats['salary_already_plaintext']],
                ['Expected Salary: Null/Empty', $stats['salary_null_or_empty']],
                ['Sales Commission: Decrypted to Plaintext', $stats['commission_decrypted']],
                ['Sales Commission: Already Plaintext', $stats['commission_already_plaintext']],
                ['Sales Commission: Null/Empty', $stats['commission_null_or_empty']],
                ['Unrecognized/Errors', $stats['errors']],
            ]
        );

        if ($stats['errors'] > 0) {
            $this->warn("Completed with {$stats['errors']} error(s).");
            return Command::FAILURE;
        }

        $this->info('All compensation data successfully reverted to plaintext.');
        return Command::SUCCESS;
    }
}
