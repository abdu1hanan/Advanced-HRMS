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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('expected_salary_amount', 12, 2)->nullable()->after('join_date');
            $table->decimal('expected_hour_per_day', 4, 2)->nullable()->after('expected_salary_amount');
            $table->decimal('expected_hours_per_week', 5, 2)->nullable()->after('expected_hour_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['expected_salary_amount', 'expected_hour_per_day', 'expected_hours_per_week']);
        });
    }
};
