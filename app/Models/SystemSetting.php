<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');
        return $value !== null ? $value : $default;
    }

    /**
     * Set a setting value by key (upsert).
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    /**
     * Get the configured number of approval levels (defaults to 1).
     */
    public static function approvalLevels(): int
    {
        return (int) static::get('approval_levels', 1);
    }
}
