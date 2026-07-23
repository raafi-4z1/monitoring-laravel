<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Pembatas percobaan login per ALAMAT IP.
 *
 * MoonShine (LoginFormRequest) sudah membatasi 5 percobaan per kombinasi username+IP, tapi
 * kunci itu per-username: penyerang bisa mencoba banyak akun berbeda dari satu IP tanpa pernah
 * terkunci (password spraying). Kelas ini menambah ember kedua yang dihitung per IP saja,
 * sehingga serangan lintas-akun dari satu sumber tetap berhenti.
 *
 * Yang dihitung hanya percobaan GAGAL, dan direset begitu ada login berhasil dari IP tsb,
 * supaya user sah yang cuma salah ketik tidak ikut terkunci.
 */
class LoginIpThrottle
{
    public const MAX_FAILURES = 10;

    public const DECAY_SECONDS = 360; // 6 menit

    public static function key(?string $ip = null): string
    {
        return 'login-ip:' . ($ip ?? request()->ip());
    }

    public static function recordFailure(?string $ip = null): void
    {
        RateLimiter::hit(self::key($ip), self::DECAY_SECONDS);
    }

    public static function clear(?string $ip = null): void
    {
        RateLimiter::clear(self::key($ip));
    }

    public static function tooManyFailures(?string $ip = null): bool
    {
        return RateLimiter::tooManyAttempts(self::key($ip), self::MAX_FAILURES);
    }

    public static function availableIn(?string $ip = null): int
    {
        return RateLimiter::availableIn(self::key($ip));
    }
}
