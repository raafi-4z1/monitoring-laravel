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

        return $response->json() ?? [];
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
