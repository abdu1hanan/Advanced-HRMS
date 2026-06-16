<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->text('pending_message_text')->nullable()->after('is_auto_appended');
            $table->timestamp('pending_override_at')->nullable()->after('pending_message_text');
        });
    }

    public function down(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropColumn(['pending_message_text', 'pending_override_at']);
        });
    }
};
