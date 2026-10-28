<?php

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Automatically set up the 3 SuperAdmin accounts (CEO, CTO, HR Department)
        (new SuperAdminSeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to preserve user accounts and prevent accidental data loss
    }
};
