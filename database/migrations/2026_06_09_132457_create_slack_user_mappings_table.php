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
        Schema::create('slack_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('slack_user_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('slack_username')->nullable();
            $table->timestamps();

            // Set up foreign key relation to users table
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_user_mappings');
    }
};
