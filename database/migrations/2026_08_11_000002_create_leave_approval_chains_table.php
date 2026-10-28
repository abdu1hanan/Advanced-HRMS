<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_approval_chains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->unsignedTinyInteger('level');      // 1 = immediate boss, 2 = boss of boss, etc.
            $table->enum('action', ['approved', 'rejected']);
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['leave_request_id', 'level']);
            $table->index(['leave_request_id', 'approver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approval_chains');
    }
};
