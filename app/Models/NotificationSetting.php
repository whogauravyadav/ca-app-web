<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NotificationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::remember("notif_setting_$key", 60, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value ?? $default;
        });
    }

    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("notif_setting_$key");
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $val = static::getValue($key, $default ? '1' : '0');

        return in_array((string) $val, ['1', 'true', 'yes', 'on'], true);
    }
}
