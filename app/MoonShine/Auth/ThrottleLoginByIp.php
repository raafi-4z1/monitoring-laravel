<?php

declare(strict_types=1);

namespace App\MoonShine\Auth;

use App\Services\ActivityLogger;
use App\Services\LoginIpThrottle;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dipasang di config/moonshine.php -> auth.pipelines, yaitu hook resmi MoonShine yang dijalankan
 * AuthenticateController SEBELUM kredensial diperiksa (lihat AuthenticateController::authenticate).
 *
 * Tujuannya menutup celah password spraying: throttle bawaan MoonShine dikunci per username+IP,
 * jadi satu IP bisa mencoba banyak akun berbeda tanpa batas. Lihat App\Services\LoginIpThrottle.
 */
class ThrottleLoginByIp
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! LoginIpThrottle::tooManyFailures()) {
            return $next($request);
        }

        $seconds = LoginIpThrottle::availableIn();

        ActivityLogger::logGuest(
            'login_blocked_ip',
            'Login diblokir: terlalu banyak percobaan gagal dari IP ini',
            [
                'retry_after_seconds' => $seconds,
                'max_failures'        => LoginIpThrottle::MAX_FAILURES,
            ]
        );

        throw ValidationException::withMessages([
            'username' => __('moonshine::auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }
}
