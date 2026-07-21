<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ElasticsearchService
{
    protected string $host;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->host     = config('elasticsearch.host', 'https://192.168.0.1:88');
        $this->username = config('elasticsearch.username', '');
        $this->password = config('elasticsearch.password', '');
    }

    public function search(string $index, array $body): array
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->withoutVerifying()
            ->post("{$this->host}/{$index}/_search", $body);

        $json = $response->json() ?? [];

        if (!empty($json['error'])) {
            $caused = $json['error']['failed_shards'][0]['reason'] ?? $json['error']['caused_by'] ?? null;
            \Illuminate\Support\Facades\Log::error("ES search error [{$index}]", [
                'status'    => $json['status'] ?? $response->status(),
                'reason'    => $json['error']['reason'] ?? null,
                'caused_by' => $caused ? ($caused['reason'] ?? json_encode($caused)) : null,
            ]);
        }

        return $json;
    }

    // ✅ Query By Sending Type dari enginenotif-ttrx-* per jam (WIB +07:00)
    public function queryBySendingType(int $sendingType, string $dateFrom, string $dateTo): array
    {
        return $this->search('enginenotif-ttrx-*', [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['sendingtype.keyword' => (string) $sendingType]],
                        [
                            'range' => [
                                'trxtime' => [
                                    'gte'       => $dateFrom . 'T00:00:00.000',
                                    'lte'       => $dateTo . 'T23:59:59.999',
                                    'time_zone' => '+07:00',
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs' => [
                'by_status' => [
                    'terms' => [
                        'field' => 'status.keyword',
                        'include' => ['0', '1'],
                    ],
                    'aggs' => [
                        'per_hour' => [
                            'date_histogram' => [
                                'field'             => 'trxtime',
                                'calendar_interval' => '1h',
                                'format'            => 'yyyy-MM-dd HH:mm',
                                'time_zone'         => '+07:00',
                                'min_doc_count'     => 1,
                            ],
                            'aggs' => [
                                'jumlah' => [
                                    'value_count' => ['field' => '_id']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    // ✅ Query Avg Response_Time dari enginenotif-ttrx-* per jam (WIB +07:00)
    public function queryAvgResponseTime(string $dateFrom, string $dateTo): array
    {
        return $this->search('enginenotif-ttrx-*', [
            'size' => 0,
            'query' => [
                'range' => [
                    'trxtime' => [
                        'gte'       => $dateFrom . 'T00:00:00.000',
                        'lte'       => $dateTo . 'T23:59:59.999',
                        'time_zone' => '+07:00',
                    ]
                ]
            ],
            'aggs' => [
                'per_hour_avg_rt' => [
                    'date_histogram' => [
                        'field'             => 'trxtime',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => [
                        'avg_responsetime' => [
                            'avg' => [
                                'script' => [
                                    'source' => "doc['sendingtime'].value.toInstant().toEpochMilli() - doc['trxtime'].value.toInstant().toEpochMilli()"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function parseStatusBuckets(array $result): array
    {
        $buckets = $result['aggregations']['by_status']['buckets'] ?? [];
        $success = [];
        $fail    = [];

        foreach ($buckets as $sb) {
            $perHour = $sb['per_hour']['buckets'] ?? [];
            foreach ($perHour as $b) {
                $hour   = $b['key_as_string']; // "2026-07-01 07:00"
                $jumlah = $b['jumlah']['value'] ?? $b['doc_count'] ?? 0;

                if ($sb['key'] === '1') {
                    $success[$hour] = ($success[$hour] ?? 0) + $jumlah;
                } elseif ($sb['key'] === '0') {
                    $fail[$hour] = ($fail[$hour] ?? 0) + $jumlah;
                }
            }
        }

        return [$success, $fail];
    }

    public function parseAvgRtBuckets(array $result): array
    {
        $buckets = $result['aggregations']['per_hour_avg_rt']['buckets'] ?? [];
        $data    = [];

        foreach ($buckets as $b) {
            $hour        = $b['key_as_string']; // "2026-07-01 07:00"
            $avgMs       = $b['avg_responsetime']['value'] ?? 0;
            $data[$hour] = round($avgMs / 1000, 2);
        }

        return $data;
    }

    // ✅ Query avg lifespan dari log-enginenotif* per jam (WIB +07:00)
    public function queryAvgLifespan(string $dateFrom, string $dateTo): array
    {
        return $this->search('log-enginenotif*', [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        // [
                        //     'term' => [
                        //         'realm.keyword' => 'stdout'
                        //     ]
                        // ],
                        [
                            'range' => [
                                'date_origin' => [
                                    'gte'       => $dateFrom . 'T00:00:00.000',
                                    'lte'       => $dateTo . 'T23:59:59.999',
                                    'time_zone' => '+07:00',
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => [
                        'field'             => 'date_origin',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => [
                        'avg_lifespan' => [
                            'avg' => [
                                'field' => 'lifespan'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function parseAvgLifespanBuckets(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $data    = [];

        foreach ($buckets as $b) {
            $hour        = $b['key_as_string']; // "2026-07-01 07:00"
            $avgLifespan = $b['avg_lifespan']['value'] ?? 0;
            $data[$hour] = round($avgLifespan, 2);
        }

        return $data;
    }

    // ✅ Query mTeleplus volume per jam (WIB +07:00)
    public function queryMteleplus(string $dateFrom, string $dateTo): array
    {
        $rules = [
            'akt' => [
                'success' => '(sms_content:("Kartu Kredit BNI Anda telah aktif*" OR "Kartu Kredit BNI Anda sudah aktif*" OR "Terima kasih, Kartu Kredit BNI Anda telah aktif*")) OR (send_to_hp:("Kartu Kredit BNI Anda telah aktif*" OR "Kartu Kredit BNI Anda sudah aktif*"))',
                'fail'    => '(sms_content:("Aktivasi Kartu Kredit BNI Anda tidak dapat kami proses*" OR "Maaf, permintaan Aktivasi Anda ditolak*")) OR (send_to_hp:("Aktivasi Kartu Kredit BNI Anda tidak dapat kami proses*" OR "Maaf, permintaan Aktivasi Anda ditolak*")) OR send_to_hp.keyword:"Aktivasi Kartu Kredit BNI Anda tidak dapat kami proses. Silakan Hubungi BNI Call 1500046." OR send_to_hp.keyword:"Maaf, transaksi Anda tidak dapat kami proses. Silakan hubungi BNI Call 1500046."',
            ],
            'rpin' => [
                'success' => '(sms_content:("Permintaan PIN berhasil*" OR "RPIN*")) OR (send_to_hp:("Permintaan PIN berhasil*")) OR (message_cc:("Permintaan PIN berhasil*"))',
                'fail'    => 'sms_content:"Maaf, transaksi RPIN anda ditolak*" OR send_to_hp:"Maaf, transaksi RPIN anda ditolak,*"',
            ],
        ];

        $subAggs = [
            'by_direction' => [
                'terms' => [
                    'field'   => 'direction.keyword',
                    'missing' => 'unknown',
                ],
            ],
        ];

        foreach ($rules as $group => $rule) {
            $subAggs["{$group}_success"] = [
                'filter' => ['query_string' => ['query' => $rule['success']]],
            ];
            $subAggs["{$group}_fail"] = [
                'filter' => ['query_string' => ['query' => $rule['fail']]],
            ];
        }

        return $this->search('log-mteleplus*', [
            'size'  => 0,
            'query' => [
                'range' => [
                    'date_origin' => [
                        'gte'       => $dateFrom . 'T00:00:00.000',
                        'lte'       => $dateTo . 'T23:59:59.999',
                        'time_zone' => '+07:00',
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => [
                        'field'             => 'date_origin',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => $subAggs,
                ],
            ],
        ]);
    }

    // ✅ Query TrxPBI Limit dari wic-trx-pbi-ceklimit* per jam, dikelompokkan per CCY2
    public function queryTrxPbiLimit(string $dateFrom, string $dateTo): array
    {
        return $this->search('wic-trx-pbi-ceklimit*', [
            'size'  => 0,
            'query' => [
                'range' => [
                    'RequestTime' => [
                        'gte'       => $dateFrom . 'T00:00:00.000',
                        'lte'       => $dateTo . 'T23:59:59.999',
                        'time_zone' => '+07:00',
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => [
                        'field'             => 'RequestTime',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => [
                        'by_currency' => [
                            'terms' => [
                                'field' => 'CCY2.keyword',
                                'size'  => 100,
                            ],
                            'aggs' => [
                                'trx_amount' => [
                                    'sum' => ['field' => 'Nominal'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    // ✅ Query TrxPBI Settlement dari log-wic-trx-pbi* per jam, dikelompokkan per currency
    public function queryTrxPbiSettlement(string $dateFrom, string $dateTo): array
    {
        return $this->search('log-wic-trx-pbi*', [
            'size'  => 0,
            'query' => [
                'range' => [
                    'DateTime' => [
                        'gte'       => $dateFrom . 'T00:00:00.000',
                        'lte'       => $dateTo . 'T23:59:59.999',
                        'time_zone' => '+07:00',
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => [
                        'field'             => 'DateTime',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => [
                        'by_currency' => [
                            'terms' => [
                                'field' => 'CCY2.keyword',
                                'size'  => 100,
                            ],
                            'aggs' => [
                                'trx_amount' => [
                                    'sum' => ['field' => 'Nominal'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function parseTrxPbiSettlement(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $bucket) {
            $hour          = $bucket['key_as_string']; // "2026-07-03 07:00"
            $parsed[$hour] = [];

            foreach ($bucket['by_currency']['buckets'] ?? [] as $ccyBucket) {
                $parsed[$hour][] = [
                    'trx_currency' => $ccyBucket['key'],
                    'trx_count'    => $ccyBucket['doc_count'],
                    'trx_amount'   => $ccyBucket['trx_amount']['value'] ?? 0,
                ];
            }
        }

        return $parsed;
    }

    // ✅ Query WIC Metric CPU dari xmb-ls* per jam per host (WIB +07:00)
    /**
     * host_ip diprioritaskan; kalau kosong, fallback filter pakai host.name (hostname).
     */
    private function wicMetricHostFilter(string $hostIp, string $hostHostname): array
    {
        // host.ip & host.name dipetakan sebagai text di index ini — harus pakai .keyword,
        // kalau tidak term query gagal match (analyzer text me-lowercase, sedangkan nilai
        // aslinya bisa uppercase seperti "WICADBDC").
        if ($hostIp !== '') {
            return ['term' => ['host.ip.keyword' => $hostIp]];
        }

        return ['term' => ['host.name.keyword' => $hostHostname]];
    }

    public function queryWicMetricCpu(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->search('xmb-ls*', [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        $this->wicMetricHostFilter($hostIp, $hostHostname),
                        ['term'  => ['metricset.name.keyword' => 'cpu']],
                        ['range' => ['@timestamp'     => ['gte' => $dateFrom . 'T00:00:00.000', 'lte' => $dateTo . 'T23:59:59.999', 'time_zone' => '+07:00']]],
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'max_pct' => ['max' => ['field' => 'system.cpu.total.norm.pct']],
                        'min_pct' => ['min' => ['field' => 'system.cpu.total.norm.pct']],
                        'avg_pct' => ['avg' => ['field' => 'system.cpu.total.norm.pct']],
                    ],
                ],
            ],
        ]);
    }

    // ✅ Query WIC Metric Memory dari xmb-ls* per jam per host (WIB +07:00)
    public function queryWicMetricMemory(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->search('xmb-ls*', [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        $this->wicMetricHostFilter($hostIp, $hostHostname),
                        ['term'  => ['metricset.name.keyword' => 'memory']],
                        ['range' => ['@timestamp'     => ['gte' => $dateFrom . 'T00:00:00.000', 'lte' => $dateTo . 'T23:59:59.999', 'time_zone' => '+07:00']]],
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'max_pct' => ['max' => ['field' => 'system.memory.actual.used.pct']],
                        'min_pct' => ['min' => ['field' => 'system.memory.actual.used.pct']],
                        'avg_pct' => ['avg' => ['field' => 'system.memory.actual.used.pct']],
                    ],
                ],
            ],
        ]);
    }

    // ✅ Query WIC Metric Disk/Filesystem dari xmb-ls* per jam per host (WIB +07:00)
    public function queryWicMetricDisk(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->search('xmb-ls*', [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        $this->wicMetricHostFilter($hostIp, $hostHostname),
                        ['term'  => ['metricset.name.keyword' => 'filesystem']],
                        ['range' => ['@timestamp'     => ['gte' => $dateFrom . 'T00:00:00.000', 'lte' => $dateTo . 'T23:59:59.999', 'time_zone' => '+07:00']]],
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'by_disk' => [
                            // mount_point dipetakan sebagai text di index ini — harus pakai .keyword
                            'terms' => ['field' => 'system.filesystem.mount_point.keyword', 'size' => 20],
                            'aggs'  => [
                                'last_doc' => [
                                    'top_hits' => [
                                        'size' => 1,
                                        'sort' => [['@timestamp' => ['order' => 'desc']]],
                                        // _source tidak dibatasi agar semua field tersedia
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function parseWicMetricCpuMemory(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $b) {
            $hour   = $b['key_as_string'];
            $maxPct = $b['max_pct']['value'] ?? null;
            $minPct = $b['min_pct']['value'] ?? null;
            $avgPct = $b['avg_pct']['value'] ?? null;

            if ($maxPct === null && $minPct === null && $avgPct === null) {
                \Illuminate\Support\Facades\Log::warning("WicMetric [cpu/memory]: semua nilai null untuk jam {$hour}, bucket dilewati.", [
                    'doc_count' => $b['doc_count'] ?? 0,
                ]);
                continue;
            }

            $parsed[$hour] = [
                'max_pct' => $maxPct,
                'min_pct' => $minPct,
                'avg_pct' => $avgPct,
            ];
        }

        return $parsed;
    }

    public function parseWicMetricDisk(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        $totalHits = $result['hits']['total']['value'] ?? $result['hits']['total'] ?? 'unknown';
        \Illuminate\Support\Facades\Log::debug('WicMetric [disk] parse', [
            'total_hits'   => $totalHits,
            'hour_buckets' => count($buckets),
            'agg_keys'     => array_keys($result['aggregations'] ?? []),
        ]);

        foreach ($buckets as $b) {
            $hour          = $b['key_as_string'];
            $parsed[$hour] = [];

            foreach ($b['by_disk']['buckets'] ?? [] as $diskBucket) {
                $hit      = $diskBucket['last_doc']['hits']['hits'][0] ?? null;
                $src      = $hit['_source'] ?? [];
                $diskPath = $diskBucket['key'];

                // Log sample source keys agar bisa diverifikasi field path-nya
                if (empty($parsed)) {
                    \Illuminate\Support\Facades\Log::debug("WicMetric [disk] sample source keys untuk '{$diskPath}' jam {$hour}", [
                        'doc_id'      => $hit['_id'] ?? null,
                        'source_keys' => array_keys($src),
                        'system_keys' => isset($src['system']) ? array_keys((array) $src['system']) : 'not_nested',
                    ]);
                }

                $lastPct        = \Illuminate\Support\Arr::get($src, 'system.filesystem.used.pct');
                $lastUsedBytes  = \Illuminate\Support\Arr::get($src, 'system.filesystem.used.bytes');
                $lastTotalBytes = \Illuminate\Support\Arr::get($src, 'system.filesystem.total');

                if ($lastPct === null) {
                    \Illuminate\Support\Facades\Log::warning("WicMetric [disk]: last_pct null untuk disk '{$diskPath}' jam {$hour}, dilewati.", [
                        'doc_id' => $hit['_id'] ?? null,
                        'src_empty' => empty($src),
                    ]);
                    continue;
                }

                $parsed[$hour][] = [
                    'disk_path'        => $diskPath,
                    'last_pct'         => $lastPct,
                    'last_used_bytes'  => $lastUsedBytes,
                    'last_total_bytes' => $lastTotalBytes,
                ];
            }

            if (empty($parsed[$hour])) {
                unset($parsed[$hour]);
            }
        }

        return $parsed;
    }

    public function parseTrxPbiLimit(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $bucket) {
            $hour          = $bucket['key_as_string']; // "2026-07-03 07:00"
            $parsed[$hour] = [];

            foreach ($bucket['by_currency']['buckets'] ?? [] as $ccyBucket) {
                $parsed[$hour][] = [
                    'trx_currency' => $ccyBucket['key'],
                    'trx_count'    => $ccyBucket['doc_count'],
                    'trx_amount'   => $ccyBucket['trx_amount']['value'] ?? 0,
                ];
            }
        }

        return $parsed;
    }

    public function parseMteleplus(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $bucket) {
            $hour     = $bucket['key_as_string']; // "2026-07-01 07:00"
            $incoming = 0;
            $outgoing = 0;

            foreach ($bucket['by_direction']['buckets'] ?? [] as $dir) {
                if ($dir['key'] === 'incoming') $incoming = $dir['doc_count'];
                if ($dir['key'] === 'outgoing') $outgoing = $dir['doc_count'];
            }

            $parsed[$hour] = [
                'akt_success'    => $bucket['akt_success']['doc_count']  ?? 0,
                'akt_fail'       => $bucket['akt_fail']['doc_count']      ?? 0,
                'rpin_success'   => $bucket['rpin_success']['doc_count']  ?? 0,
                'rpin_fail'      => $bucket['rpin_fail']['doc_count']     ?? 0,
                'total_incoming' => $incoming,
                'total_outgoing' => $outgoing,
            ];
        }

        return $parsed;
    }

    // ✅ Query TrxPBI Loader (batch job) dari wic-data-core* — dokumen mentah.
    //
    // Beda dari query lain: field `trx_time` di index ini bertipe `text` (format
    // "DD/MM/YYYY HH:MM:SS"), bukan `date`, jadi TIDAK bisa di-date_histogram di ES.
    // Solusinya tarik dokumen mentah lalu group per jam di PHP (lihat parseTrxPbiLoader).
    // Aman karena volumenya kecil (ribuan dokumen, bukan jutaan).
    //
    // Range difilter di `@datelog` (timestamp asli) dengan pelebaran ±20 menit, karena
    // trx_time bisa sedikit beda dengan waktu log-nya. Penyaringan tanggal yang presisi
    // dilakukan di PHP berdasarkan trx_time.
    public function queryTrxPbiLoader(string $dateFrom, string $dateTo): array
    {
        return $this->search('wic-data-core*', [
            'size'    => 10000,
            '_source' => ['trx_time', 'filename', 'keyword', 'success_row'],
            'query'   => [
                'bool' => [
                    'filter' => [
                        ['terms' => ['keyword.keyword' => ['DATA BERHASIL PROSES', 'DATA GAGAL PROSES']]],
                        ['exists' => ['field' => 'trx_time']],
                        ['range' => [
                            '@datelog' => [
                                'gte'       => $dateFrom . 'T00:00:00.000||-20m',
                                'lte'       => $dateTo . 'T23:59:59.999||+20m',
                                'time_zone' => '+07:00',
                            ],
                        ]],
                    ],
                    'must_not' => [
                        ['term' => ['trx_time.keyword' => '-']],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Parse hasil queryTrxPbiLoader jadi agregat per (tanggal, jam, status).
     *
     * Tiap dokumen = 1 run batch: trx_time = waktu mulai, timestamp di nama file
     * (extrf1sp_YYYYMMDD_HHMMSS.txt) = waktu selesai.
     *
     * start_time/end_time/duration_sec = rata-rata dalam grup. record_processed:
     * - status success: SUM(success_row) — satu dokumen "DATA BERHASIL PROSES" bisa
     *   mewakili >1 baris sukses yang cuma ditulis 1 baris log.
     * - status failed: COUNT dokumen "DATA GAGAL PROSES" — setiap kegagalan ditulis
     *   sebagai baris log tersendiri, jadi 1 dokumen = 1 kegagalan. Tapi untuk
     *   start_time/end_time/duration_sec, beberapa dokumen gagal dari filename yang
     *   sama digabung dulu jadi 1 titik data (start dirata-rata, end selalu sama)
     *   supaya 1 batch run tidak membobot rata-rata jam lebih berat dari batch run lain.
     *
     * @param  list<string>  $onlyDates  Batasi ke tanggal ini saja (Y-m-d), hasil filter presisi by trx_time
     * @return array<string, array<string, mixed>>  key: "Y-m-d|H|status"
     */
    public function parseTrxPbiLoader(array $result, array $onlyDates = []): array
    {
        $groups = [];

        foreach ($result['hits']['hits'] ?? [] as $hit) {
            $src      = $hit['_source'] ?? [];
            $trxTime  = trim((string) ($src['trx_time'] ?? ''));
            $filename = (string) ($src['filename'] ?? '');

            $start = $this->parseTrxPbiLoaderTrxTime($trxTime);
            $end   = $this->parseTrxPbiLoaderFilenameTime($filename);

            // trx_time wajib ada & valid — dokumen tanpa itu diabaikan (sesuai spesifikasi)
            if ($start === null) {
                continue;
            }

            $dateStr = $start->format('Y-m-d');

            if ($onlyDates !== [] && ! in_array($dateStr, $onlyDates, true)) {
                continue;
            }

            $status = ($src['keyword'] ?? '') === 'DATA BERHASIL PROSES' ? 'success' : 'failed';
            $hour   = (int) $start->format('G');
            $key    = $dateStr . '|' . $hour . '|' . $status;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'trx_date'         => $dateStr,
                    'trx_hour'         => $hour,
                    'status_job'       => $status,
                    'record_processed' => 0,
                    '_start_secs'      => [],
                    '_end_secs'        => [],
                    '_failed_files'    => [],
                ];
            }

            $groups[$key]['record_processed'] += $status === 'success'
                ? (int) ($src['success_row'] ?? 0)
                : 1;

            // start_time & end_time HARUS dihitung dari dokumen yang sama persis (bukan
            // himpunan terpisah) supaya keduanya konsisten & end_time tidak pernah lebih
            // awal dari start_time.
            if ($end !== null) {
                $duration = $end->getTimestamp() - $start->getTimestamp();

                if ($duration >= 0) {
                    if ($status === 'failed') {
                        // 1 filename bisa punya beberapa baris "DATA GAGAL PROSES" (1 baris
                        // log per kegagalan). Kumpulkan dulu per filename supaya nanti cuma
                        // jadi 1 titik data ke rata-rata grup — bukan 1 titik per baris gagal,
                        // supaya file yang gagal berkali-kali tidak membobot rata-rata lebih berat.
                        $groups[$key]['_failed_files'][$filename]['starts'][] = $this->secondsOfDay($start);
                        $groups[$key]['_failed_files'][$filename]['end'] = $this->secondsOfDay($end);
                    } else {
                        $groups[$key]['_start_secs'][] = $this->secondsOfDay($start);
                        $groups[$key]['_end_secs'][] = $this->secondsOfDay($end);
                    }
                }
            }
        }

        foreach ($groups as $key => $g) {
            foreach ($g['_failed_files'] as $file) {
                $groups[$key]['_start_secs'][] = (int) round(array_sum($file['starts']) / count($file['starts']));
                $groups[$key]['_end_secs'][] = $file['end'];
            }
        }

        $parsed = [];

        foreach ($groups as $key => $g) {
            $avgStart = $g['_start_secs'] !== [] ? (int) round(array_sum($g['_start_secs']) / count($g['_start_secs'])) : null;
            $avgEnd   = $g['_end_secs'] !== [] ? (int) round(array_sum($g['_end_secs']) / count($g['_end_secs'])) : null;
            // duration_sec diturunkan dari end_time - start_time (bukan dirata-rata sendiri)
            // supaya selalu sinkron dengan yang tampil di start_time/end_time.
            $avgDur = ($avgStart !== null && $avgEnd !== null) ? max(0, $avgEnd - $avgStart) : 0;

            $parsed[$key] = [
                'trx_date'               => $g['trx_date'],
                'trx_hour'               => $g['trx_hour'],
                'status_job'             => $g['status_job'],
                'start_time'             => $avgStart !== null ? $this->secondsToTime($avgStart) : null,
                'end_time'               => $avgEnd !== null ? $this->secondsToTime($avgEnd) : null,
                'duration_sec'           => $avgDur,
                'record_processed'       => $g['record_processed'],
                'throughput_row_per_sec' => $avgDur > 0 ? round($g['record_processed'] / $avgDur, 2) : 0.0,
            ];
        }

        return $parsed;
    }

    // trx_time formatnya "DD/MM/YYYY HH:MM:SS" (mis. "09/07/2026 15:41:58")
    private function parseTrxPbiLoaderTrxTime(string $value): ?\DateTimeImmutable
    {
        if ($value === '' || $value === '-') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $value);

        return $dt instanceof \DateTimeImmutable ? $dt : null;
    }

    // nama file: "extrf1sp_YYYYMMDD_HHMMSS.txt" (mis. "extrf1sp_20260709_154213.txt")
    private function parseTrxPbiLoaderFilenameTime(string $filename): ?\DateTimeImmutable
    {
        if (! preg_match('/(\d{8})_(\d{6})/', $filename, $m)) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Ymd His', $m[1] . ' ' . $m[2]);

        return $dt instanceof \DateTimeImmutable ? $dt : null;
    }

    private function secondsOfDay(\DateTimeImmutable $dt): int
    {
        return ((int) $dt->format('G')) * 3600 + ((int) $dt->format('i')) * 60 + (int) $dt->format('s');
    }

    private function secondsToTime(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600) % 24, intdiv($seconds % 3600, 60), $seconds % 60);
    }

    // Mapping query_string ELK -> nama service yang ditampilkan
    private const SYSTEM_ONLINE_SERVICE_MAP = [
        'WICService/WICService.svc' => 'SVC Service',
        'WIC/Account/Login'         => 'Login',
    ];

    // ✅ Query System Online dari wic-access* per jam, dikelompokkan per query_string (service)
    public function querySystemOnline(string $dateFrom, string $dateTo): array
    {
        return $this->search('wic-access-*', [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['terms' => ['query_string.keyword' => array_keys(self::SYSTEM_ONLINE_SERVICE_MAP)]],
                        ['range' => [
                            'date_origin' => [
                                'gte'       => $dateFrom . 'T00:00:00.000',
                                'lte'       => $dateTo . 'T23:59:59.999',
                                'time_zone' => '+07:00',
                            ],
                        ]],
                    ],
                ],
            ],
            'aggs' => [
                'per_hour' => [
                    'date_histogram' => [
                        'field'             => 'date_origin',
                        'calendar_interval' => '1h',
                        'format'            => 'yyyy-MM-dd HH:mm',
                        'time_zone'         => '+07:00',
                        'min_doc_count'     => 1,
                    ],
                    'aggs' => [
                        'by_query_string' => [
                            'terms' => [
                                'field' => 'query_string.keyword',
                                'size'  => 10,
                            ],
                            'aggs' => [
                                'avg_time_taken' => [
                                    'avg' => ['field' => 'time_taken'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Parse hasil querySystemOnline jadi agregat per (jam, service_name).
     *
     * @return array<string, array<int, array{service_name: string, response_time_avg_ms: float}>>  key: "Y-m-d H:i"
     */
    public function parseSystemOnline(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $bucket) {
            $hour          = $bucket['key_as_string']; // "2026-07-17 07:00"
            $parsed[$hour] = [];

            foreach ($bucket['by_query_string']['buckets'] ?? [] as $qsBucket) {
                $queryString = $qsBucket['key'];

                $parsed[$hour][] = [
                    'service_name'         => self::SYSTEM_ONLINE_SERVICE_MAP[$queryString] ?? $queryString,
                    'response_time_avg_ms' => round($qsBucket['avg_time_taken']['value'] ?? 0, 2),
                ];
            }
        }

        return $parsed;
    }
}
