<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Models\MoonshineUser;
use Throwable;

/**
 * Pencatat aktivitas user (siapa, kapan, dari IP mana, ngapain) — dipakai sebagai jejak audit
 * kalau ada yang perlu ditelusuri (data berubah tak terduga, permission salah, dsb).
 *
 * Sengaja fail-safe: kalau proses logging sendiri error, jangan sampai menggagalkan aksi asli
 * user (cukup dicatat ke log aplikasi biasa).
 */
class ActivityLogger
{
    private const REDACTED = '[hidden]';

    /**
     * Potongan nama kolom yang nilainya tidak boleh ikut tersimpan di audit trail.
     * remember_token khususnya penting: nilainya cukup untuk membajak sesi user
     * (cookie remember-me), dan log ini bisa ikut ter-export ke CSV.
     */
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'api_key', 'apikey', 'authorization', 'credential'];

    public static function log(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        try {
            $user = auth(moonshineConfig()->getGuard())->user();

            ActivityLog::create([
                'user_id'      => $user instanceof MoonshineUser ? $user->id : null,
                'user_name'    => $user->name ?? null,
                'user_email'   => $user->email ?? null,
                'ip_address'   => request()->ip(),
                'action'       => $action,
                'subject_type' => $subject !== null ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'description'  => $description,
                'properties'   => self::redact($properties) ?: null,
            ]);
        } catch (Throwable $e) {
            Log::channel('daily')->error("ActivityLogger: gagal mencatat aktivitas '{$action}' - {$e->getMessage()}");
        }
    }

    /**
     * Log tanpa user terautentikasi (mis. percobaan login gagal) — user_id/name/email kosong,
     * tapi IP tetap dicatat supaya masih bisa dilacak sumbernya.
     */
    public static function logGuest(string $action, string $description, array $properties = []): void
    {
        try {
            ActivityLog::create([
                'ip_address'  => request()->ip(),
                'action'      => $action,
                'description' => $description,
                'properties'  => self::redact($properties) ?: null,
            ]);
        } catch (Throwable $e) {
            Log::channel('daily')->error("ActivityLogger: gagal mencatat aktivitas guest '{$action}' - {$e->getMessage()}");
        }
    }

    private static function redact(array $properties): array
    {
        array_walk_recursive($properties, function (&$value, $key) {
            if (! is_string($key)) {
                return;
            }

            $lower = strtolower($key);

            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($lower, $needle)) {
                    $value = self::REDACTED;

                    return;
                }
            }
        });

        return $properties;
    }
}
