<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('expected_salary_amount')->nullable()->change();
            $table->text('sales_commission')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @throws RuntimeException If encrypted ciphertext is detected to prevent silent data destruction.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $hasEncrypted = DB::table('users')
                ->where(function ($query) {
                    $query->whereNotNull('expected_salary_amount')
                          ->whereRaw("expected_salary_amount NOT REGEXP '^[0-9]+$'")
                          ->orWhere(function ($sub) {
                              $sub->whereNotNull('sales_commission')
                                  ->whereRaw("sales_commission NOT REGEXP '^[0-9]+$'");
                          });
                })
                ->exists();
        } else {
            $hasEncrypted = DB::table('users')
                ->select(['expected_salary_amount', 'sales_commission'])
                ->get()
                ->contains(function ($row) {
                    $sal = $row->expected_salary_amount;
                    $comm = $row->sales_commission;
                    return ($sal !== null && !ctype_digit((string) $sal)) ||
                           ($comm !== null && !ctype_digit((string) $comm));
                });
        }

        if ($hasEncrypted) {
            throw new RuntimeException(
                'Cannot revert compensation columns to unsignedBigInteger: encrypted ciphertext detected in users table. ' .
                'Run `php artisan salary:decrypt-existing` first before rolling back this migration to prevent permanent data destruction.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('expected_salary_amount')->nullable()->change();
            $table->unsignedBigInteger('sales_commission')->nullable()->change();
        });
    }
};
