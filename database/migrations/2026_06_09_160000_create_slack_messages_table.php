<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slack_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('slack_user_id');
            $table->string('channel_id');
            $table->text('message_text');
            $table->string('slack_ts');
            $table->timestamp('message_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_messages');
    }
};
