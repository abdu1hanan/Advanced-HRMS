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
            ['name' => Role::STAFF,       'label' => 'Staff'],
            ['name' => Role::GUEST,       'label' => 'Guest'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
