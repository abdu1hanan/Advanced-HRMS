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

        // 3. HR Department: hr@beetleops.com (special_order: 3)
        // Shift legacy admin (e.g. admin@company.com) if hr@beetleops.com does not exist yet
        $hrUser = User::where('email', 'hr@beetleops.com')->first();
        if (!$hrUser) {
            $legacyAdmin = User::where('email', 'admin@company.com')
                ->orWhere(function ($q) use ($superAdminRoleId) {
                    $q->where('role_id', $superAdminRoleId)
                      ->whereNotIn('email', ['ammar@beetleops.com', 'rana.saad@beetleops.com']);
                })
                ->first();

            if ($legacyAdmin) {
                $legacyAdmin->update([
                    'name'              => 'HR Department',
                    'email'             => 'hr@beetleops.com',
                    'role_id'           => $superAdminRoleId,
                    'designation_id'    => $hrDesig->id,
                    'special_order'     => 3,
                    'password'          => $legacyAdmin->password ?: Hash::make('admin'),
                    'email_verified_at' => $legacyAdmin->email_verified_at ?: now(),
                ]);
            } else {
                User::create([
                    'name'              => 'HR Department',
                    'email'             => 'hr@beetleops.com',
                    'password'          => Hash::make('admin'),
                    'role_id'           => $superAdminRoleId,
                    'designation_id'    => $hrDesig->id,
                    'special_order'     => 3,
                    'email_verified_at' => now(),
                ]);
            }
        } else {
            $hrUser->update([
                'name'              => 'HR Department',
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $hrDesig->id,
                'special_order'     => 3,
                'password'          => $hrUser->password ?: Hash::make('admin'),
                'email_verified_at' => $hrUser->email_verified_at ?: now(),
            ]);
        }

        // 4. CEO: ammar@beetleops.com (special_order: 1)
        $ammarUser = User::where('email', 'ammar@beetleops.com')->first();
        if (!$ammarUser) {
            User::create([
                'name'              => 'Ammar',
                'email'             => 'ammar@beetleops.com',
                'password'          => Hash::make('admin'),
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ceoDesig->id,
                'special_order'     => 1,
                'email_verified_at' => now(),
            ]);
        } else {
            $ammarUser->update([
                'name'              => 'Ammar',
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ceoDesig->id,
                'special_order'     => 1,
                'password'          => $ammarUser->password ?: Hash::make('admin'),
                'email_verified_at' => $ammarUser->email_verified_at ?: now(),
            ]);
        }

        // 5. CTO: rana.saad@beetleops.com (special_order: 2)
        $saadUser = User::where('email', 'rana.saad@beetleops.com')->first();
        if (!$saadUser) {
            User::create([
                'name'              => 'Rana Saad',
                'email'             => 'rana.saad@beetleops.com',
                'password'          => Hash::make('admin'),
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ctoDesig->id,
                'special_order'     => 2,
                'email_verified_at' => now(),
            ]);
        } else {
            $saadUser->update([
                'name'              => 'Rana Saad',
                'role_id'           => $superAdminRoleId,
                'designation_id'    => $ctoDesig->id,
                'special_order'     => 2,
                'password'          => $saadUser->password ?: Hash::make('admin'),
                'email_verified_at' => $saadUser->email_verified_at ?: now(),
            ]);
        }
    }
}
