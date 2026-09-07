<?php

return [
    'host'     => env('GRAFANA_HOST', ''),
    'port'     => env('GRAFANA_PORT', ''),
    'username' => env('GRAFANA_USERNAME', ''),
    'password' => env('GRAFANA_PASSWORD', ''),

    // Batas tunggu per permintaan (detik). Sebelumnya tidak diset sama sekali sehingga memakai
    // default Laravel 30 detik - dan itu terbukti kurang: pada 07-09-2026 backfill Space-X NYC
    // gagal di 2 tanggal terakhir dengan "cURL error 28 ... 0 bytes received", padahal tanggal
    // yang sama berhasil dalam 3-4 detik saat backfill diulang 90 detik kemudian. Jadi bukan
    // query-nya berat (terukur cuma ~0.5 detik saat senggang), melainkan stall sesaat ketika
    // banyak agregasi berat ditembakkan berturut-turut.
    'timeout' => (int) env('GRAFANA_TIMEOUT', 60),

    // Ulangi otomatis kalau koneksi gagal/timeout. Tanpa ini, satu stall sesaat membuat data
    // hari itu HILANG (dicatat error lalu dilewati) dan harus di-backfill manual - persis yang
    // terjadi pada insiden di atas. Jalur ini tidak pernah dipakai interaktif (cuma fetch
    // terjadwal/manual), jadi menunggu beberapa detik untuk mencoba lagi jauh lebih baik
    // daripada kehilangan data.
    'retry_times'    => (int) env('GRAFANA_RETRY_TIMES', 3),
    'retry_delay_ms' => (int) env('GRAFANA_RETRY_DELAY_MS', 2000),

    // UID datasource per index Elasticsearch yang di-proxy lewat Grafana. Satu Grafana bisa
    // punya banyak datasource (per server/lokasi) - tambah key baru di sini kalau ada lagi.
    'datasources' => [
        'reportingkcln'     => env('GRAFANA_REPORTINGKCLN_UID', ''),
        'metricbeat_elkhub' => env('GRAFANA_METRICBEAT_ELKHUB_UID', ''),
    ],
];
