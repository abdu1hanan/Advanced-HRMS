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
            $table->unsignedBigInteger('expected_salary_amount')->nullable()->change();
            $table->unsignedInteger('expected_hour_per_day')->nullable()->change();
            $table->unsignedInteger('expected_hours_per_week')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('expected_salary_amount', 12, 2)->nullable()->change();
            $table->decimal('expected_hour_per_day', 4, 2)->nullable()->change();
            $table->decimal('expected_hours_per_week', 5, 2)->nullable()->change();
        });
    }
};
