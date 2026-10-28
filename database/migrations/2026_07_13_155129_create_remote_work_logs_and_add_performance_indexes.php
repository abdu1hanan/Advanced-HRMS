<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create remote_work_logs table if not exists
        if (!Schema::hasTable('remote_work_logs')) {
            Schema::create('remote_work_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->string('notes', 500)->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'date']);
                $table->index('date');
            });
        }

        // 2. Add performance indexes for holiday query checks
        try {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->index(['status', 'start_date', 'end_date']);
            });
        } catch (\Exception $e) {
            // Index already exists or query failed
        }

        try {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index('date');
            });
        } catch (\Exception $e) {
            // Index already exists or query failed
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropIndex(['date']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex(['status', 'start_date', 'end_date']);
            });
        } catch (\Exception $e) {}

        Schema::dropIfExists('remote_work_logs');
    }
};
