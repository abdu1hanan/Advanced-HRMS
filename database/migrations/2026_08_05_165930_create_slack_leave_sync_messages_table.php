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
        Schema::create('slack_leave_sync_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->unique();
            $table->string('slack_user_id');
            $table->string('channel_id');
            $table->text('message_text');
            $table->string('slack_ts');
            $table->dateTime('message_at');
            $table->boolean('is_edited')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_leave_sync_messages');
    }
};
