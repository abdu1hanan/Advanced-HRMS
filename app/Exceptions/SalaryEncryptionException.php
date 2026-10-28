<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class SalaryEncryptionException extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self(
            'Salary encryption key is not configured. Please define the `encrypt_key` environment variable.'
        );
    }

    public static function malformedKey(): self
    {
        return new self(
            'Salary encryption key is malformed. Expected format: base64:<base64-encoded-key>.'
        );
    }

    public static function invalidKeyLength(int $actual, int $expected): self
    {
        return new self(
            "Salary encryption key length is invalid. Expected {$expected} bytes for the configured cipher, got {$actual} bytes."
        );
    }

    public static function decryptionFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Decryption of compensation value failed: {$reason}.",
            0,
            $previous
        );
    }
}
