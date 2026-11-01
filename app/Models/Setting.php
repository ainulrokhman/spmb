<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Read a setting value, falling back to the given default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    /**
     * Store a setting value.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Read a boolean setting ("1"/"0" strings), with a default.
     */
    public static function flag(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : $value === '1';
    }
}
