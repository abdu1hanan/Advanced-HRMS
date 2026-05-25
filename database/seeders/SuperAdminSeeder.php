<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = Role::idByName(Role::SUPER_ADMIN);

        User::updateOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin'),
                'role_id' => $roleId,
                'email_verified_at' => now(),
            ]
        );
    }
}
