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
        if ($hostIp !== '') {
            return ['term' => ['host.ip' => $hostIp]];
        }

        return ['term' => ['host.name' => $hostHostname]];
    }

    public function queryWicMetricCpu(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->search('xmb-ls*', [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        $this->wicMetricHostFilter($hostIp, $hostHostname),
                        ['term'  => ['metricset.name' => 'cpu']],
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
                        ['term'  => ['metricset.name' => 'memory']],
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
                        ['term'  => ['metricset.name' => 'filesystem']],
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
}
