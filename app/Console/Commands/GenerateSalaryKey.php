<?php

namespace App\Console\Commands;

use App\Services\SalaryEncrypter;
use Illuminate\Console\Command;

class GenerateSalaryKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:generate-key
                            {--cipher=AES-256-CBC : Cipher algorithm to generate key for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a cryptographically secure random key for salary encryption';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cipher = (string) $this->option('cipher');
        $key = SalaryEncrypter::generateKey($cipher);

        $this->info('Cryptographically secure salary encryption key generated:');
        $this->line("<comment>{$key}</comment>");
        $this->newLine();
        $this->warn('IMPORTANT: Copy this key and add it to your .env file as:');
        $this->line("<info>encrypt_key={$key}</info>");
        $this->newLine();
        $this->warn('CRITICAL: Securely back up this key outside the server/repository.');
        $this->warn('If this key is permanently lost, all encrypted salary data cannot be recovered.');

        return Command::SUCCESS;
    }
}
