<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\LeaveType;
use App\Models\LeaveSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $type = LeaveType::updateOrCreate(
            ['slug' => 'unpaid'],
            [
                'name' => 'Unpaid Leave',
                'max_days_per_year' => 999, // practically unlimited
                'allow_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'requires_attachment' => false,
                'is_active' => true,
            ]
        );

        LeaveSetting::updateOrCreate(
            ['leave_type_id' => $type->id],
            ['default_entitled' => 0]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Allow it to remain
    }
};
