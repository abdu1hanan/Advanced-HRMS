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
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifiable_read_at');
            $table->index(['notifiable_type', 'notifiable_id', 'is_marked'], 'idx_notifiable_is_marked');
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'idx_notifiable_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifiable_read_at');
            $table->dropIndex('idx_notifiable_is_marked');
            $table->dropIndex('idx_notifiable_created_at');
        });
    }
};
