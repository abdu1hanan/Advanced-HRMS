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
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->string('target_type')->default('all')->after('date'); // 'all', 'department', 'employee'
            $table->string('target_action')->default('include')->after('target_type'); // 'include', 'exclude'
        });

        Schema::create('company_holiday_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_holiday_id')->constrained('company_holidays')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['company_holiday_id', 'department_id'], 'ch_dept_unique');
        });

        Schema::create('company_holiday_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_holiday_id')->constrained('company_holidays')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['company_holiday_id', 'user_id'], 'ch_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_holiday_users');
        Schema::dropIfExists('company_holiday_departments');

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_action']);
        });
    }
};
