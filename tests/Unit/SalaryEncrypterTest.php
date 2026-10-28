<?php

namespace Tests\Unit;

use App\Exceptions\SalaryEncryptionException;
use App\Services\SalaryEncrypter;
use PHPUnit\Framework\TestCase;

class SalaryEncrypterTest extends TestCase
{
    protected string $validKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validKey = SalaryEncrypter::generateKey();
    }

    public function test_fails_when_key_is_missing(): void
    {
        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Salary encryption key is not configured');

        new SalaryEncrypter(null);
    }

    public function test_fails_when_key_is_empty_string(): void
    {
        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Salary encryption key is not configured');

        new SalaryEncrypter('   ');
    }

    public function test_fails_when_base64_key_is_malformed(): void
    {
        $this->expectException(SalaryEncryptionException::class);
        // Either malformed or invalid length
        new SalaryEncrypter('base64:???not-valid-base64???');
    }

    public function test_fails_when_key_length_is_too_short(): void
    {
        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Salary encryption key length is invalid');

        // 16 bytes for AES-256 is too short
        $shortKey = 'base64:' . base64_encode(random_bytes(16));
        new SalaryEncrypter($shortKey, 'AES-256-CBC');
    }

    public function test_accepts_valid_base64_encoded_256_bit_key(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $this->assertInstanceOf(SalaryEncrypter::class, $encrypter);
        $this->assertEquals('AES-256-CBC', $encrypter->getCipher());
    }

    public function test_accepts_valid_raw_32_byte_key(): void
    {
        $rawKey = random_bytes(32);
        $encrypter = new SalaryEncrypter($rawKey);
        $this->assertInstanceOf(SalaryEncrypter::class, $encrypter);
    }

    public function test_encrypts_and_decrypts_standard_salary_value(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $plaintext = '150000';

        $ciphertext = $encrypter->encrypt($plaintext);

        $this->assertNotEmpty($ciphertext);
        $this->assertNotEquals($plaintext, $ciphertext);
        $this->assertEquals($plaintext, $encrypter->decrypt($ciphertext));
    }

    public function test_encrypts_and_decrypts_zero_correctly(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);

        $ciphertext = $encrypter->encrypt('0');

        $this->assertNotEmpty($ciphertext);
        $this->assertNotEquals('0', $ciphertext);
        $this->assertEquals('0', $encrypter->decrypt($ciphertext));
    }

    public function test_encrypts_and_decrypts_large_salary_value(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $largeSalary = '50000000';

        $ciphertext = $encrypter->encrypt($largeSalary);

        $this->assertNotEmpty($ciphertext);
        $this->assertEquals($largeSalary, $encrypter->decrypt($ciphertext));
    }

    public function test_null_and_empty_round_trip_safely(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);

        $this->assertNull($encrypter->encrypt(null));
        $this->assertNull($encrypter->encrypt(''));
        $this->assertNull($encrypter->decrypt(null));
        $this->assertNull($encrypter->decrypt(''));
    }

    public function test_different_ciphertexts_produced_for_same_plaintext_due_to_random_iv(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);

        $c1 = $encrypter->encrypt('150000');
        $c2 = $encrypter->encrypt('150000');

        $this->assertNotEquals($c1, $c2);
        $this->assertEquals('150000', $encrypter->decrypt($c1));
        $this->assertEquals('150000', $encrypter->decrypt($c2));
    }

    public function test_tampered_ciphertext_fails_decryption(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $ciphertext = $encrypter->encrypt('150000');

        // Tamper with a character in the payload
        $tampered = substr_replace($ciphertext, 'X', 12, 1);

        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Decryption of compensation value failed');

        $encrypter->decrypt($tampered);
    }

    public function test_decryption_fails_with_different_key(): void
    {
        $encrypter1 = new SalaryEncrypter($this->validKey);
        $encrypter2 = new SalaryEncrypter(SalaryEncrypter::generateKey());

        $ciphertext = $encrypter1->encrypt('150000');

        $this->expectException(SalaryEncryptionException::class);
        $this->expectExceptionMessage('Decryption of compensation value failed');

        $encrypter2->decrypt($ciphertext);
    }

    public function test_is_encrypted_identifies_ciphertext_accurately(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $ciphertext = $encrypter->encrypt('150000');

        $this->assertTrue($encrypter->isEncrypted($ciphertext));
        $this->assertFalse($encrypter->isEncrypted('150000'));
        $this->assertFalse($encrypter->isEncrypted('0'));
        $this->assertFalse($encrypter->isEncrypted(null));
        $this->assertFalse($encrypter->isEncrypted(''));
        $this->assertFalse($encrypter->isEncrypted('not-a-valid-base64-payload'));

        // Tampered ciphertext fails authenticated check
        $tampered = substr_replace($ciphertext, 'Z', 15, 1);
        $this->assertFalse($encrypter->isEncrypted($tampered));
    }

    public function test_is_legacy_plaintext_identifies_numeric_values(): void
    {
        $encrypter = new SalaryEncrypter($this->validKey);
        $ciphertext = $encrypter->encrypt('150000');

        $this->assertTrue($encrypter->isLegacyPlaintext(150000));
        $this->assertTrue($encrypter->isLegacyPlaintext(0));
        $this->assertTrue($encrypter->isLegacyPlaintext('150000'));
        $this->assertTrue($encrypter->isLegacyPlaintext('0'));
        $this->assertTrue($encrypter->isLegacyPlaintext('150000.00'));

        $this->assertFalse($encrypter->isLegacyPlaintext(null));
        $this->assertFalse($encrypter->isLegacyPlaintext(''));
        $this->assertFalse($encrypter->isLegacyPlaintext($ciphertext));
        $this->assertFalse($encrypter->isLegacyPlaintext('invalid_string'));
    }
}
