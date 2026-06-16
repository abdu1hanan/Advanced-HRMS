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
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->string('approval_status')->nullable()->after('is_edited'); // 'pending', 'approved', 'rejected'
            $table->timestamp('original_message_at')->nullable()->after('approval_status');
            $table->timestamp('override_at')->nullable()->after('original_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'original_message_at', 'override_at']);
        });
    }
};
