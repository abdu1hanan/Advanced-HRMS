<?php

namespace Tests\Feature;

use App\Exceptions\SalaryEncryptionException;
use App\Models\User;
use App\Services\SalaryEncrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptedSalaryCastTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create();
    }

    public function test_setting_salary_stores_ciphertext_in_raw_db_state(): void
    {
        $this->user->expected_salary_amount = 150000;
        $this->user->save();

        // 1. Check via Eloquent raw original
        $rawSalary = $this->user->getRawOriginal('expected_salary_amount');
        $this->assertNotEquals('150000', $rawSalary);
        $this->assertNotEquals(150000, $rawSalary);
        $this->assertTrue(app(SalaryEncrypter::class)->isEncrypted($rawSalary));

        // 2. Check directly from database query builder
        $dbRow = DB::table('users')->where('id', $this->user->id)->first();
        $this->assertEquals($rawSalary, $dbRow->expected_salary_amount);
    }

    public function test_reading_salary_returns_original_plaintext_integer(): void
    {
        $this->user->expected_salary_amount = 275000;
        $this->user->save();

        $freshUser = User::find($this->user->id);
        $this->assertSame(275000, $freshUser->expected_salary_amount);
        $this->assertIsInt($freshUser->expected_salary_amount);
    }

    public function test_sales_commission_behaves_identically(): void
    {
        $this->user->sales_commission = 45000;
        $this->user->save();

        $rawCommission = $this->user->getRawOriginal('sales_commission');
        $this->assertNotEquals(45000, $rawCommission);
        $this->assertTrue(app(SalaryEncrypter::class)->isEncrypted($rawCommission));

        $freshUser = User::find($this->user->id);
        $this->assertSame(45000, $freshUser->sales_commission);
        $this->assertIsInt($freshUser->sales_commission);
    }

    public function test_null_round_trips_correctly(): void
    {
        $this->user->expected_salary_amount = null;
        $this->user->sales_commission = null;
        $this->user->save();

        $rawRow = DB::table('users')->where('id', $this->user->id)->first();
        $this->assertNull($rawRow->expected_salary_amount);
        $this->assertNull($rawRow->sales_commission);

        $freshUser = User::find($this->user->id);
        $this->assertNull($freshUser->expected_salary_amount);
        $this->assertNull($freshUser->sales_commission);
    }

    public function test_zero_round_trips_correctly(): void
    {
        $this->user->expected_salary_amount = 0;
        $this->user->sales_commission = 0;
        $this->user->save();

        $rawSalary = $this->user->getRawOriginal('expected_salary_amount');
        $rawCommission = $this->user->getRawOriginal('sales_commission');

        $this->assertTrue(app(SalaryEncrypter::class)->isEncrypted($rawSalary));
        $this->assertTrue(app(SalaryEncrypter::class)->isEncrypted($rawCommission));

        $freshUser = User::find($this->user->id);
        $this->assertSame(0, $freshUser->expected_salary_amount);
        $this->assertSame(0, $freshUser->sales_commission);
    }

    public function test_large_salary_values_round_trip_correctly(): void
    {
        $largeSalary = 50000000; // 50 million
        $this->user->expected_salary_amount = $largeSalary;
        $this->user->save();

        $freshUser = User::find($this->user->id);
        $this->assertSame($largeSalary, $freshUser->expected_salary_amount);
    }

    public function test_floating_numbers_are_normalized_to_integer(): void
    {
        $this->user->expected_salary_amount = 185000.00;
        $this->user->save();

        $freshUser = User::find($this->user->id);
        $this->assertSame(185000, $freshUser->expected_salary_amount);
    }

    public function test_legacy_plaintext_values_remain_readable_during_migration_phase(): void
    {
        // Directly update DB with raw plaintext numeric value (bypassing model cast)
        DB::table('users')->where('id', $this->user->id)->update([
            'expected_salary_amount' => '120000',
            'sales_commission'       => '15000',
        ]);

        $freshUser = User::find($this->user->id);

        $this->assertSame(120000, $freshUser->expected_salary_amount);
        $this->assertSame(150000, $freshUser->expected_salary_amount + 30000);
        $this->assertSame(15000, $freshUser->sales_commission);
    }

    public function test_tampered_ciphertext_in_db_causes_decryption_failure(): void
    {
        $this->user->expected_salary_amount = 150000;
        $this->user->save();

        $rawCipher = $this->user->getRawOriginal('expected_salary_amount');
        $tamperedCipher = substr_replace($rawCipher, 'X', 12, 1);

        DB::table('users')->where('id', $this->user->id)->update([
            'expected_salary_amount' => $tamperedCipher,
        ]);

        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Decryption of compensation value failed');

        $freshUser = User::find($this->user->id);
        // Accessing the attribute must fail
        $val = $freshUser->expected_salary_amount;
    }

    public function test_does_not_double_encrypt_if_ciphertext_passed_to_setter(): void
    {
        $encrypter = app(SalaryEncrypter::class);
        $ciphertext = $encrypter->encrypt('200000');

        $this->user->expected_salary_amount = $ciphertext;
        $this->user->save();

        $rawSalary = $this->user->getRawOriginal('expected_salary_amount');
        $this->assertEquals($ciphertext, $rawSalary);

        $freshUser = User::find($this->user->id);
        $this->assertSame(200000, $freshUser->expected_salary_amount);
    }
}
