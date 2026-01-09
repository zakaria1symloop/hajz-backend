<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AdminSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("admin_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value, ?string $description = null): static
    {
        Cache::forget("admin_setting_{$key}");

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    public static function getCommissionRate(): float
    {
        return (float) static::get('commission_rate', 10);
    }

    public static function setCommissionRate(float $rate): static
    {
        return static::set('commission_rate', $rate, 'Commission percentage taken from each booking');
    }
}
