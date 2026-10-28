<?php

namespace Database\Seeders;

use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Super Admin role exists
        $superAdminRoleId = Role::idByName(Role::SUPER_ADMIN);

        if (!$superAdminRoleId) {
            $role = Role::firstOrCreate(
                ['name' => Role::SUPER_ADMIN],
                ['label' => 'Super Admin']
            );
            $superAdminRoleId = $role->id;
        }

        // 2. Ensure Designations exist
        $ceoDesig = Designation::firstOrCreate(['name' => 'CEO']);
        $ctoDesig = Designation::firstOrCreate(['name' => 'CTO']);
        $hrDesig  = Designation::firstOrCreate(['name' => 'HR Manager']);

        // 3. Ensure Departments exist for executive roles
        $mgtDept = \App\Models\Department::firstOrCreate(['code' => 'MGT'], ['name' => 'Management Department']);
        $devDept = \App\Models\Department::firstOrCreate(['code' => 'DEV'], ['name' => 'Development Department']);
        $hrDept  = \App\Models\Department::firstOrCreate(['code' => 'HR'], ['name' => 'HR Department']);

        // 4. CEO: ceo@company.com (special_order: 1)
        User::updateOrCreate(
            ['email' => 'ceo@company.com'],
            [
                'name'              => 'Chief Executive Officer',
                'password'          => Hash::make('admin'),
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ceoDesig->id,
                'department_id'     => $mgtDept->id,
                'special_order'     => 1,
                'email_verified_at' => now(),
            ]
        );

        // 5. CTO: cto@company.com (special_order: 2)
        User::updateOrCreate(
            ['email' => 'cto@company.com'],
            [
                'name'              => 'Chief Technology Officer',
                'password'          => Hash::make('admin'),
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ctoDesig->id,
                'department_id'     => $devDept->id,
                'special_order'     => 2,
                'email_verified_at' => now(),
            ]
        );

        // 6. HR Department: hr@company.com (special_order: 3)
        User::updateOrCreate(
            ['email' => 'hr@company.com'],
            [
                'name'              => 'HR Department',
                'password'          => Hash::make('admin'),
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $hrDesig->id,
                'department_id'     => $hrDept->id,
                'special_order'     => 3,
                'email_verified_at' => now(),
            ]
        );
    }
}
