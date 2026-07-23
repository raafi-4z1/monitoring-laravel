<?php

declare(strict_types=1);

/**
 * Folder tujuan export CSV terjadwal, per jenis laporan.
 *
 * Nilainya sengaja dibaca di sini, BUKAN lewat env() langsung di dalam command.
 * Setelah `php artisan config:cache` dijalankan (langkah standar di production),
 * Laravel tidak lagi memuat file .env, sehingga env() di luar folder config/
 * mengembalikan null dan diam-diam jatuh ke nilai default — CSV harian akan
 * tertulis ke folder yang salah tanpa error apa pun.
 */
return [
    'trx_pbi'        => env('TRX_PBI_EXPORT_PATH')        ?: storage_path('app/exports'),
    'trx_pbi_loader' => env('TRX_PBI_LOADER_EXPORT_PATH') ?: storage_path('app/exports'),
    'wic_metric'     => env('WIC_METRIC_EXPORT_PATH')     ?: storage_path('app/exports'),
    'system_online'  => env('SYSTEM_ONLINE_EXPORT_PATH')  ?: storage_path('app/exports'),
];
