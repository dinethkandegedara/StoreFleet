<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'scope_id',
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            $setting->clearCache();
        });

        static::deleted(function (Setting $setting) {
            $setting->clearCache();
        });
    }

    public function clearCache(): void
    {
        $cacheKey = self::getCacheKey($this->scope, $this->scope_id);
        Cache::forget($cacheKey);
    }

    public static function getCacheKey(?string $scope = 'business', ?int $scopeId = null): string
    {
        $scopeName = $scope ?: 'business';
        $idPart = $scopeId !== null ? (string) $scopeId : 'global';

        return "settings_{$scopeName}_{$idPart}";
    }

    /**
     * Retrieve all settings for a specific scope as key-value pairs.
     *
     * @return array<string, mixed>
     */
    public static function allForScope(string $scope = 'business', ?int $scopeId = null): array
    {
        $cacheKey = self::getCacheKey($scope, $scopeId);

        return Cache::remember($cacheKey, 86400, function () use ($scope, $scopeId) {
            try {
                return static::where('scope', $scope)
                    ->where('scope_id', $scopeId)
                    ->pluck('value', 'key')
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Get a setting value with fallback default.
     */
    public static function get(string $key, mixed $default = null, string $scope = 'business', ?int $scopeId = null): mixed
    {
        $settings = static::allForScope($scope, $scopeId);

        return $settings[$key] ?? $default;
    }

    /**
     * Persist or update a setting value.
     */
    public static function set(string $key, mixed $value, string $scope = 'business', ?int $scopeId = null): self
    {
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value);

        return static::updateOrCreate(
            [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'key' => $key,
            ],
            [
                'value' => $stringValue,
            ]
        );
    }
}
