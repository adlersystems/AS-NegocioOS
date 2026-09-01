<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'type'])]
class Setting extends Model
{
    use RecordsActivity;

    public const CACHE_KEY = 'app_settings';

    /** @var array<string, string> Default values keyed by setting key. */
    public const DEFAULTS = [
        'company_name' => 'AS-NegocioOS',
        'logo' => null,
        'address' => null,
        'phone' => null,
        'nit' => null,
        'email' => null,
        'currency' => 'GTQ',
        'iva_percentage' => '12',
        'default_language' => 'es',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::getAll();

        return $settings[$key] ?? (self::DEFAULTS[$key] ?? $default);
    }

    public static function set(string $key, mixed $value, string $type = 'text'): void
    {
        self::updateOrCreate(['key' => $key], [
            'value' => is_scalar($value) ? (string) $value : json_encode($value),
            'type' => $type,
        ]);

        self::forgetCache();
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    /** @return array<string, string> */
    public static function getAll(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = self::query()->pluck('value', 'key')->all();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function currencySymbol(?string $currency = null): string
    {
        $currency ??= self::get('currency', 'GTQ');

        return match ($currency) {
            'USD' => '$',
            'GTQ' => 'Q',
            default => $currency,
        };
    }

    public static function formatMoney(float|string $amount): string
    {
        return self::currencySymbol().' '.number_format((float) $amount, 2);
    }
}
