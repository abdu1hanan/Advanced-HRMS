<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SalaryEncrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalaryEncryptExistingCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user1;
    protected User $user2;
    protected User $user3;

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::limit(3)->get();
        if ($users->count() < 3) {
            $users = User::factory()->count(3)->create();
        }

        $this->user1 = $users[0];
        $this->user2 = $users[1];
        $this->user3 = $users[2];
    }

    public function test_command_converts_plaintext_compensation_to_ciphertext(): void
    {
        // Populate DB with raw legacy plaintext values
        DB::table('users')->where('id', $this->user1->id)->update([
            'expected_salary_amount' => '150000',
            'sales_commission'       => '25000',
        ]);
        DB::table('users')->where('id', $this->user2->id)->update([
            'expected_salary_amount' => '0',
            'sales_commission'       => null,
        ]);
        DB::table('users')->where('id', $this->user3->id)->update([
            'expected_salary_amount' => null,
            'sales_commission'       => '0',
        ]);

        // Run encryption command
        $this->artisan('salary:encrypt-existing', ['--chunk' => 10])
            ->assertExitCode(0);

        // Verify raw DB values are now ciphertext
        $encrypter = app(SalaryEncrypter::class);

        $row1 = DB::table('users')->where('id', $this->user1->id)->first();
        $this->assertTrue($encrypter->isEncrypted($row1->expected_salary_amount));
        $this->assertTrue($encrypter->isEncrypted($row1->sales_commission));
        $this->assertEquals('150000', $encrypter->decrypt($row1->expected_salary_amount));
        $this->assertEquals('25000', $encrypter->decrypt($row1->sales_commission));

        $row2 = DB::table('users')->where('id', $this->user2->id)->first();
        $this->assertTrue($encrypter->isEncrypted($row2->expected_salary_amount));
        $this->assertEquals('0', $encrypter->decrypt($row2->expected_salary_amount));
        $this->assertNull($row2->sales_commission);

        $row3 = DB::table('users')->where('id', $this->user3->id)->first();
        $this->assertNull($row3->expected_salary_amount);
        $this->assertTrue($encrypter->isEncrypted($row3->sales_commission));
        $this->assertEquals('0', $encrypter->decrypt($row3->sales_commission));

        // Verify Eloquent model reads them transparently
        $fresh1 = User::find($this->user1->id);
        $this->assertSame(150000, $fresh1->expected_salary_amount);
        $this->assertSame(25000, $fresh1->sales_commission);

        $fresh2 = User::find($this->user2->id);
        $this->assertSame(0, $fresh2->expected_salary_amount);
        $this->assertNull($fresh2->sales_commission);
    }

    public function test_command_is_idempotent_and_never_double_encrypts(): void
    {
        // Set raw plaintext
        DB::table('users')->where('id', $this->user1->id)->update([
            'expected_salary_amount' => '175000',
            'sales_commission'       => '10000',
        ]);

        // First run: encrypts
        $this->artisan('salary:encrypt-existing', ['--chunk' => 10])
            ->assertExitCode(0);

        $rowAfterRun1 = DB::table('users')->where('id', $this->user1->id)->first();
        $cipherSalary1 = $rowAfterRun1->expected_salary_amount;
        $cipherComm1 = $rowAfterRun1->sales_commission;

        // Second run: must detect already encrypted and NOT modify
        $this->artisan('salary:encrypt-existing', ['--chunk' => 10])
            ->expectsOutputToContain('Expected Salary: Already Encrypted')
            ->assertExitCode(0);

        $rowAfterRun2 = DB::table('users')->where('id', $this->user1->id)->first();
        $this->assertEquals($cipherSalary1, $rowAfterRun2->expected_salary_amount);
        $this->assertEquals($cipherComm1, $rowAfterRun2->sales_commission);

        // Value still decrypts correctly to 175000 and 10000
        $fresh = User::find($this->user1->id);
        $this->assertSame(175000, $fresh->expected_salary_amount);
        $this->assertSame(10000, $fresh->sales_commission);
    }

    public function test_dry_run_option_does_not_modify_database(): void
    {
        DB::table('users')->where('id', $this->user1->id)->update([
            'expected_salary_amount' => '300000',
            'sales_commission'       => '50000',
        ]);

        $this->artisan('salary:encrypt-existing', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertExitCode(0);

        // Values should still be plaintext
        $row = DB::table('users')->where('id', $this->user1->id)->first();
        $this->assertEquals('300000', $row->expected_salary_amount);
        $this->assertEquals('50000', $row->sales_commission);
    }

    public function test_decrypt_command_reverts_ciphertext_to_plaintext_numbers(): void
    {
        // First encrypt via model
        $this->user1->expected_salary_amount = 220000;
        $this->user1->sales_commission = 18000;
        $this->user1->save();

        $this->assertTrue(app(SalaryEncrypter::class)->isEncrypted(
            $this->user1->getRawOriginal('expected_salary_amount')
        ));

        // Run decrypt command
        $this->artisan('salary:decrypt-existing', ['--force' => true])
            ->assertExitCode(0);

        $row = DB::table('users')->where('id', $this->user1->id)->first();
        $this->assertEquals(220000, $row->expected_salary_amount);
        $this->assertEquals(18000, $row->sales_commission);

        // Model still reads correctly
        $fresh = User::find($this->user1->id);
        $this->assertSame(220000, $fresh->expected_salary_amount);
        $this->assertSame(18000, $fresh->sales_commission);
    }
}
