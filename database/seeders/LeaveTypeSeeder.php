<?php

namespace Database\Seeders;

use App\Models\LeaveSetting;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Unpaid Leave',
                'slug' => 'unpaid',
                'max_days_per_year' => 999,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'default_entitled' => 0,
            ],
            [
                'name' => 'Emergency',
                'slug' => 'emergency',
                'max_days_per_year' => 999,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'default_entitled' => 0,
            ],
            [
                'name' => 'Absent',
                'slug' => 'absent',
                'max_days_per_year' => 999,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'default_entitled' => 0,
            ],
            [
                'name' => 'Annual Leave',
                'slug' => 'annual',
                'max_days_per_year' => 5,
                'allow_carry_forward' => true,
                'max_carry_forward_days' => 2,
                'default_entitled' => 5,
            ],
            [
                'name' => 'Casual Leave',
                'slug' => 'casual',
                'max_days_per_year' => 10,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'default_entitled' => 10,
            ],
            [
                'name' => 'Sick Leave',
                'slug' => 'sick',
                'max_days_per_year' => 5,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'default_entitled' => 5,
            ],
        ];

        foreach ($types as $data) {
            $entitled = $data['default_entitled'];
            unset($data['default_entitled']);

            $type = LeaveType::updateOrCreate(['slug' => $data['slug']], $data);

            LeaveSetting::updateOrCreate(
                ['leave_type_id' => $type->id],
                ['default_entitled' => $entitled]
            );
        }
    }
}
