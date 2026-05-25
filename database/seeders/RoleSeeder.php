<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => Role::SUPER_ADMIN, 'label' => 'Super Admin'],
            ['name' => Role::ADMIN, 'label' => 'Admin (Line Manager)'],
            ['name' => Role::EMPLOYEE, 'label' => 'Employee'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
