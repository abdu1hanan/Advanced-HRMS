<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Snapshot of how many manager levels must approve (captured at submit time)
            $table->unsignedTinyInteger('approval_chain_target')->default(1)->after('admin_comment');
            // How many levels have been approved so far
            $table->unsignedTinyInteger('approval_chain_progress')->default(0)->after('approval_chain_target');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['approval_chain_target', 'approval_chain_progress']);
        });
    }
};
