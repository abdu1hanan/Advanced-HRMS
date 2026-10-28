<?php

namespace App\Services;

use App\Exceptions\SalaryEncryptionException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Throwable;

class SalaryEncrypter
{
    protected Encrypter $encrypter;
    protected string $cipher;

    public function __construct(?string $key = null, string $cipher = 'AES-256-CBC')
    {
        $this->cipher = $cipher;
        $parsedKey = $this->parseAndValidateKey($key, $cipher);
        $this->encrypter = new Encrypter($parsedKey, $cipher);
    }

    /**
     * Parse, decode, and validate the encryption key.
     *
     * @throws SalaryEncryptionException
     */
    protected function parseAndValidateKey(?string $rawKey, string $cipher): string
    {
        if ($rawKey === null || trim($rawKey) === '') {
            throw SalaryEncryptionException::missingKey();
        }

        $rawKey = trim($rawKey);
        $key = $rawKey;

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                throw SalaryEncryptionException::malformedKey();
            }
            $key = $decoded;
        }

        $expectedSize = match (strtolower($cipher)) {
            'aes-128-cbc', 'aes-128-gcm' => 16,
            'aes-256-cbc', 'aes-256-gcm' => 32,
            default => 32,
        };

        $actualSize = mb_strlen($key, '8bit');

        if ($actualSize !== $expectedSize || !Encrypter::supported($key, $cipher)) {
            throw SalaryEncryptionException::invalidKeyLength($actualSize, $expectedSize);
        }

        return $key;
    }

    /**
     * Encrypt a salary/compensation string value.
     */
    public function encrypt(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->encrypter->encryptString((string) $value);
    }

    /**
     * Decrypt an encrypted compensation payload string.
     *
     * @throws SalaryEncryptionException
     */
    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            return $this->encrypter->decryptString($payload);
        } catch (DecryptException $e) {
            throw SalaryEncryptionException::decryptionFailed($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw SalaryEncryptionException::decryptionFailed('Malformed or corrupt payload', $e);
        }
    }

    /**
     * Robustly determine whether a stored DB value is already ciphertext
     * encrypted with this dedicated encrypter.
     */
    public function isEncrypted(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        // Fast rejection if clearly numeric
        if (preg_match('/^\d+(\.\d+)?$/', trim($value))) {
            return false;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);
        if (!is_array($json) || !isset($json['iv'], $json['value'], $json['mac'])) {
            return false;
        }

        // Authenticated verification: try decrypting with our encrypter
        try {
            $this->encrypter->decryptString($value);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine whether a stored DB value is a legacy unencrypted numeric salary.
     */
    public function isLegacyPlaintext(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' && preg_match('/^\d+(\.\d+)?$/', $trimmed) === 1;
        }

        return false;
    }

    /**
     * Generate a cryptographically secure random key for the configured cipher.
     */
    public static function generateKey(string $cipher = 'AES-256-CBC'): string
    {
        return 'base64:' . base64_encode(Encrypter::generateKey($cipher));
    }

    /**
     * Get the underlying Laravel Encrypter instance.
     */
    public function getEncrypter(): Encrypter
    {
        return $this->encrypter;
    }

    /**
     * Get the configured cipher algorithm.
     */
    public function getCipher(): string
    {
        return $this->cipher;
    }
}
