<?php

namespace App\Casts;

use App\Services\SalaryEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EncryptedSalary implements CastsAttributes
{
    protected SalaryEncrypter $encrypter;

    public function __construct(?SalaryEncrypter $encrypter = null)
    {
        $this->encrypter = $encrypter ?? app(SalaryEncrypter::class);
    }

    /**
     * Cast the given raw database value to the application-level numeric salary.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return int|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Temporary backwards compatibility: if the raw database value is clearly
        // a legacy unencrypted numeric value (e.g. during schema migration window),
        // return it directly as an integer.
        if ($this->encrypter->isLegacyPlaintext($value)) {
            return (int) round((float) $value);
        }

        // Genuine ciphertext: decrypt using the dedicated salary encrypter.
        // If the ciphertext is malformed or tampered with, decrypt() throws
        // SalaryEncryptionException (never silently treated as plaintext).
        $decrypted = $this->encrypter->decrypt((string) $value);

        return $decrypted !== null ? (int) round((float) $decrypted) : null;
    }

    /**
     * Transform the given numeric salary into encrypted ciphertext for database storage.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Prevent double encryption if already valid ciphertext
        if (is_string($value) && $this->encrypter->isEncrypted($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $normalized = (string) (int) round((float) $value);
            return $this->encrypter->encrypt($normalized);
        }

        throw new InvalidArgumentException("The {$key} compensation value must be numeric or null.");
    }
}
