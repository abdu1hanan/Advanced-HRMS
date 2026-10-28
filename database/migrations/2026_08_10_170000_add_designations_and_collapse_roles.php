<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create designations table
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Add designation_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('designation_id')
                ->nullable()
                ->after('manager_id')
                ->constrained('designations')
                ->nullOnDelete();
        });

        // 3. Collapse admin + employee roles into a single 'staff' role
        //    a) Insert the new staff role if it doesn't exist
        $staffId = DB::table('roles')->where('name', 'staff')->value('id');
        if (! $staffId) {
            $staffId = DB::table('roles')->insertGetId([
                'name'       => 'staff',
                'label'      => 'Staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        //    b) Move all admin + employee users to the new staff role
        $oldRoleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'employee'])
            ->pluck('id')
            ->toArray();

        if (! empty($oldRoleIds)) {
            DB::table('users')
                ->whereIn('role_id', $oldRoleIds)
                ->whereNull('deleted_at')
                ->update(['role_id' => $staffId, 'updated_at' => now()]);

            // Also update soft-deleted users
            DB::table('users')
                ->whereIn('role_id', $oldRoleIds)
                ->update(['role_id' => $staffId, 'updated_at' => now()]);

            // Delete old roles
            DB::table('roles')->whereIn('name', ['admin', 'employee'])->delete();
        }

        // 4. Drop head_user_id from departments
        Schema::table('departments', function (Blueprint $table) {
            // Drop the foreign key first (SQLite ignores this but needed for MySQL/Postgres)
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['head_user_id']);
            }
            $table->dropColumn('head_user_id');
        });

        // 5. Clear manager_id for any users whose manager is now invalid (safety pass)
        //    All manager_ids should still point to valid users — nothing to clean here structurally.
        //    But admin-role users previously had manager_id = null, employees had it set.
        //    The new model keeps whatever manager_id was set. No reset needed.
    }

    public function down(): void
    {
        // Restore head_user_id on departments
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Restore old roles
        $adminId = DB::table('roles')->insertGetId([
            'name' => 'admin', 'label' => 'Admin (Line Manager)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $employeeId = DB::table('roles')->insertGetId([
            'name' => 'employee', 'label' => 'Employee',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Move staff users back to employee role
        $staffId = DB::table('roles')->where('name', 'staff')->value('id');
        if ($staffId) {
            DB::table('users')->where('role_id', $staffId)->update(['role_id' => $employeeId]);
            DB::table('roles')->where('name', 'staff')->delete();
        }

        // Remove designation_id from users
        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['designation_id']);
            }
            $table->dropColumn('designation_id');
        });

        Schema::dropIfExists('designations');
    }
};
