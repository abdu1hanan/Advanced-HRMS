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
        Schema::table('leave_request_comments', function (Blueprint $table) {
            $table->boolean('is_hidden_from_employee')->default(false)->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_request_comments', function (Blueprint $table) {
            $table->dropColumn('is_hidden_from_employee');
        });
    }
};
