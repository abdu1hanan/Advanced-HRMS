<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->index(['message_at', 'id'], 'idx_slack_messages_at_id');
            $table->index(['slack_user_id', 'message_at'], 'idx_slack_messages_user_at');
            $table->index(['approval_status', 'message_at'], 'idx_slack_messages_status_at');
        });
    }

    public function down(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropIndex('idx_slack_messages_at_id');
            $table->dropIndex('idx_slack_messages_user_at');
            $table->dropIndex('idx_slack_messages_status_at');
        });
    }
};
