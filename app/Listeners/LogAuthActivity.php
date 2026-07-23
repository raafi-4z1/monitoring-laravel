<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\ActivityLogger;
use App\Services\LoginIpThrottle;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        // Login sah dari IP ini -> reset hitungan gagal, supaya user yang cuma salah ketik
        // tidak menumpuk kuota sampai ikut terkunci.
        LoginIpThrottle::clear();

        $name = $event->user->name ?? $event->user->email ?? 'unknown';
        ActivityLogger::log('login', "Login berhasil: {$name}", $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $name = $event->user->name ?? $event->user->email ?? 'unknown';
        ActivityLogger::log('logout', "Logout: {$name}", $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        LoginIpThrottle::recordFailure();

        $username = $event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown';
        ActivityLogger::logGuest('login_failed', "Percobaan login gagal: {$username}", ['guard' => $event->guard]);
    }

    /**
     * Lockout bawaan MoonShine (5x gagal untuk satu username+IP). Dicatat terpisah dari
     * login_failed supaya percobaan brute force satu akun langsung kelihatan di audit trail.
     */
    public function handleLockout(Lockout $event): void
    {
        $username = $event->request->input('username', 'unknown');

        ActivityLogger::logGuest(
            'login_lockout',
            "Akun terkunci sementara karena terlalu banyak percobaan gagal: {$username}"
        );
    }
}
