<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * One-time codes for changing the email or phone on a signed-in account.
 *
 * A code only works for the user, the purpose and the new value it was sent to,
 * and is thrown away after {@see self::MAX_ATTEMPTS} wrong tries.
 */
class AccountOtp
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const TTL_SECONDS = 600;

    public const MAX_ATTEMPTS = 5;

    public static function store(User $user, string $purpose, string $target, string $code): void
    {
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(self::cacheKey($user, $purpose), [
            'target' => $target,
            'hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);
    }

    public static function verify(User $user, string $purpose, string $target, string $code): bool
    {
        $key = self::cacheKey($user, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry) || $entry['target'] !== $target) {
            return false;
        }

        if (Hash::check($code, $entry['hash'])) {
            return true;
        }

        $entry['attempts']++;

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
        } else {
            Cache::put($key, $entry, max(1, $entry['expires_at'] - now()->getTimestamp()));
        }

        return false;
    }

    public static function forget(User $user, string $purpose): void
    {
        Cache::forget(self::cacheKey($user, $purpose));
    }

    private static function cacheKey(User $user, string $purpose): string
    {
        return "account-otp:{$purpose}:{$user->id}";
    }
}
