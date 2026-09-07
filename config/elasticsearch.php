<?php

return [
    'host'     => env('ES_HOST', 'https://192.168.0.1:88'),
    'username' => env('ES_USERNAME', ''),
    'password' => env('ES_PASSWORD', ''),

    // Batas tunggu per permintaan (detik). Sengaja tanpa retry - beda dari config('grafana'),
    // karena jalur ini juga melayani permintaan interaktif dari bot Telegram, di mana gagal
    // cepat lebih baik daripada user menunggu beberapa kali timeout berturut-turut.
    'timeout' => (int) env('ES_TIMEOUT', 30),
];