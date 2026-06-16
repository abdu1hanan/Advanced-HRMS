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
            $table->boolean('is_edited')->default(false)->after('message_text');
        });

        Schema::create('slack_message_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_message_id')->constrained('slack_messages')->onDelete('cascade');
            $table->text('previous_text');
            $table->text('new_text');
            $table->timestamp('edited_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_message_edits');
        
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropColumn('is_edited');
        });
    }
};
