<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
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
        $username = $event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown';
        ActivityLogger::logGuest('login_failed', "Percobaan login gagal: {$username}", ['guard' => $event->guard]);
    }
}
