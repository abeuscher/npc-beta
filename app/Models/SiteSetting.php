<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type'];

    /**
     * Read a setting from cache/DB, returning $default if the key doesn't exist.
     * Value is cast based on the row's `type` column.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("site_setting:{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if ($setting === null) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Write a setting and flush its cache key.
     * For encrypted types, the value is encrypted before storage.
     */
    public static function set(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();

        $storedValue = ($setting?->type === 'encrypted' && filled($value))
            ? Crypt::encryptString((string) $value)
            : $value;

        if ($setting) {
            $setting->update(['value' => $storedValue]);
        } else {
            static::create(['key' => $key, 'value' => $storedValue]);
        }

        Cache::forget("site_setting:{$key}");
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer'   => (int) $value,
            'json'      => json_decode($value, true),
            'encrypted' => filled($value) ? (function () use ($value) {
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return '';
                }
            })() : '',
            default     => $value,
        };
    }
}
