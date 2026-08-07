<?php

return [
    'host'     => env('GRAFANA_HOST', ''),
    'port'     => env('GRAFANA_PORT', ''),
    'username' => env('GRAFANA_USERNAME', ''),
    'password' => env('GRAFANA_PASSWORD', ''),

    // UID datasource per index Elasticsearch yang di-proxy lewat Grafana. Satu Grafana bisa
    // punya banyak datasource (per server/lokasi) - tambah key baru di sini kalau ada lagi.
    'datasources' => [
        'reportingkcln'     => env('GRAFANA_REPORTINGKCLN_UID', ''),
        'metricbeat_elkhub' => env('GRAFANA_METRICBEAT_ELKHUB_UID', ''),
    ],
];
