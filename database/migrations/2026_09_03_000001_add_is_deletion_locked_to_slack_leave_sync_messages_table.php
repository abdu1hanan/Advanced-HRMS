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
        Schema::table('slack_leave_sync_messages', function (Blueprint $table) {
            $table->boolean('is_deletion_locked')->default(false)->after('is_edited');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slack_leave_sync_messages', function (Blueprint $table) {
            $table->dropColumn('is_deletion_locked');
        });
    }
};
