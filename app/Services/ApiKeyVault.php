<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class ApiKeyVault
{
    private const CACHE_TTL = 3600; // 1 hour

    public function get(string $provider, int $userId): ?string
    {
        return Cache::remember(
            "api_key.{$userId}.{$provider}",
            self::CACHE_TTL,
            function () use ($provider, $userId) {
                $apiKey = ApiKey::where('provider', $provider)
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();

                return $apiKey?->decrypted_key;
            }
        );
    }

    public function set(string $provider, string $key, int $userId): ApiKey
    {
        $apiKey = ApiKey::updateOrCreate(
            ['provider' => $provider, 'user_id' => $userId],
            [
                'encrypted_key' => Crypt::encryptString($key),
                'is_active' => true,
            ]
        );

        Cache::forget("api_key.{$userId}.{$provider}");

        return $apiKey;
    }

    public function has(string $provider, int $userId): bool
    {
        return $this->get($provider, $userId) !== null;
    }

    public function allConfigured(int $userId): bool
    {
        return $this->has('anthropic', $userId)
            && $this->has('nano_banana', $userId)
            && $this->has('kling', $userId);
    }

    public function getStatus(int $userId): array
    {
        $providers = ['anthropic', 'nano_banana', 'kling'];
        $status = [];

        foreach ($providers as $provider) {
            $apiKey = ApiKey::where('provider', $provider)
                ->where('user_id', $userId)
                ->first();
            $status[$provider] = [
                'configured' => $apiKey !== null,
                'is_active' => $apiKey?->is_active ?? false,
                'last_verified_at' => $apiKey?->last_verified_at?->toISOString(),
            ];
        }

        return $status;
    }

    public function remove(string $provider, int $userId): void
    {
        ApiKey::where('provider', $provider)->where('user_id', $userId)->delete();
        Cache::forget("api_key.{$userId}.{$provider}");
    }
}
