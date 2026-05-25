<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\HierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\LeaveTypeSeeder::class,
        ]);
    }

    public function test_replacing_department_head_links_previous_head_in_same_department(): void
    {
        $dept = Department::factory()->create(['name' => 'Engineering', 'code' => 'ENG']);

        $alice = User::factory()->admin()->create([
            'department_id' => $dept->id,
        ]);
        $bob = User::factory()->admin()->create([
            'department_id' => $dept->id,
        ]);
        $employee = User::factory()->employee()->create([
            'department_id' => $dept->id,
        ]);

        $service = app(HierarchyService::class);
        $service->replaceDepartmentHead($dept, $alice);
        $service->replaceDepartmentHead($dept->fresh(), $bob);

        $alice->refresh();
        $employee->refresh();

        $this->assertEquals($bob->id, $dept->fresh()->head_user_id);
        $this->assertEquals($bob->id, $alice->manager_id);
        $this->assertEquals($bob->id, $employee->manager_id);
    }
}
