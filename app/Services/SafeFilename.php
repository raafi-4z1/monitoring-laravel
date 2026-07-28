<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pembersih potongan nama file export.
 *
 * Nama file CSV harian dirangkai dari kolom report_sources (kode_prefix, app_id,
 * service_integrator) yang bisa diedit dari panel admin. Nilainya disambung langsung ke path,
 * jadi tanpa pembersihan sebuah nilai seperti "../../../../Windows/Temp/x" akan membuat file
 * ditulis DI LUAR folder export yang dikonfigurasi.
 *
 * Perlu akses admin untuk mengeditnya, jadi ini bukan celah naik-hak-akses — tapi tetap
 * ditutup sebagai lapis pertahanan tambahan, sekaligus mencegah export gagal diam-diam
 * gara-gara salah ketik (mis. ada '/' atau ':' yang tidak sah sebagai nama file di Windows).
 */
class SafeFilename
{
    /**
     * Sisakan hanya karakter yang aman dipakai sebagai nama file.
     *
     * Spasi ikut diizinkan supaya nilai yang diisi admin (mis. service_integrator
     * "Engine Notif") tidak berubah diam-diam, tapi spasi/titik di ujung dipangkas: titik di
     * depan mencegah ".." dan file tersembunyi, sedangkan spasi/titik di belakang bermasalah
     * sebagai nama file di Windows.
     */
    public static function segment(?string $value, string $fallback = 'UNKNOWN'): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._\- ]/', '', (string) $value) ?? '';
        $clean = trim($clean, " \t\n\r\0\x0B.");

        return $clean !== '' ? $clean : $fallback;
    }
}
