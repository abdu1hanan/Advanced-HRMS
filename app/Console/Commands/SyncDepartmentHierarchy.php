<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncDepartmentHierarchy extends Command
{
    protected $signature = 'hrms:sync-hierarchy';

    protected $description = '[Deprecated] No longer needed. Hierarchy is now managed via dynamic manager_id assignments on each user record.';

    public function handle(): int
    {
        $this->info('This command is no longer needed. Boss assignments are now managed per-user via manager_id.');
        $this->info('Use the User Management panel in the Super Admin portal to assign immediate bosses.');

        return self::SUCCESS;
    }
}
