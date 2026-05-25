<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Services\HierarchyService;
use Illuminate\Console\Command;

class SyncDepartmentHierarchy extends Command
{
    protected $signature = 'hrms:sync-hierarchy';

    protected $description = 'Re-link all employees to their department line managers based on current department heads';

    public function handle(HierarchyService $hierarchyService): int
    {
        $count = 0;

        foreach (Department::whereNotNull('head_user_id')->get() as $department) {
            $hierarchyService->syncEmployeesToDepartmentHead($department);
            $count++;
        }

        $this->info("Synced employees for {$count} department(s) with assigned heads.");

        return self::SUCCESS;
    }
}
