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

    // ✅ Query By Sending Type dari enginenotif-ttrx-* per hari
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
                                    'gte' => $dateFrom,
                                    'lte' => $dateTo,
                                    'format' => 'yyyy-MM-dd',
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
                        'per_day' => [
                            'date_histogram' => [
                                'field' => 'trxtime',
                                'calendar_interval' => '1d',
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

    // ✅ Query Avg Response_Time dari enginenotif-ttrx-* per hari
    public function queryAvgResponseTime(string $dateFrom, string $dateTo): array
    {
        return $this->search('enginenotif-ttrx-*', [
            'size' => 0,
            'query' => [
                'range' => [
                    'trxtime' => [
                        'gte' => $dateFrom,
                        'lte' => $dateTo,
                        'format' => 'yyyy-MM-dd',
                    ]
                ]
            ],
            'aggs' => [
                'per_day_avg_rt' => [
                    'date_histogram' => [
                        'field' => 'trxtime',
                        'calendar_interval' => '1d',
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
            $perDay = $sb['per_day']['buckets'] ?? [];
            foreach ($perDay as $b) {
                $date   = substr($b['key_as_string'], 0, 10);
                $jumlah = $b['jumlah']['value'] ?? $b['doc_count'] ?? 0;

                if ($sb['key'] === '1') {
                    $success[$date] = $jumlah;
                } elseif ($sb['key'] === '0') {
                    $fail[$date] = $jumlah;
                }
            }
        }

        return [$success, $fail];
    }

    public function parseAvgRtBuckets(array $result): array
    {
        $buckets = $result['aggregations']['per_day_avg_rt']['buckets'] ?? [];
        $data    = [];

        foreach ($buckets as $b) {
            $date           = substr($b['key_as_string'], 0, 10);
            $avgMs          = $b['avg_responsetime']['value'] ?? 0;
            $data[$date]    = round($avgMs / 1000, 2);
        }

        return $data;
    }

    // ✅ Query avg lifespan dari log-enginenotif* per hari
    public function queryAvgLifespan(string $dateFrom, string $dateTo): array
    {
        return $this->search('log-enginenotif*', [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'realm.keyword' => 'stdout'
                            ]
                        ],
                        [
                            'range' => [
                                'date_origin' => [
                                    'gte'    => $dateFrom,
                                    'lte'    => $dateTo,
                                    'format' => 'yyyy-MM-dd',
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs' => [
                'per_day' => [
                    'date_histogram' => [
                        'field'             => 'date_origin',
                        'calendar_interval' => '1d',
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
        $buckets = $result['aggregations']['per_day']['buckets'] ?? [];
        $data    = [];

        foreach ($buckets as $b) {
            $date        = substr($b['key_as_string'], 0, 10);
            $avgLifespan = $b['avg_lifespan']['value'] ?? 0;
            $data[$date] = round($avgLifespan, 2);
        }

        return $data;
    }

    // ✅ Query mTeleplus volume per hari (aggregation)
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

        // ✅ Build sub-aggs untuk direction dan rule filters
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
                        'gte'    => $dateFrom,
                        'lte'    => $dateTo,
                        'format' => 'yyyy-MM-dd',
                    ],
                ],
            ],
            'aggs' => [
                'per_day' => [
                    'date_histogram' => [
                        'field'             => 'date_origin',
                        'calendar_interval' => '1d',
                        'min_doc_count'     => 0,
                    ],
                    'aggs' => $subAggs,
                ],
            ],
        ]);
    }
    
    public function parseMteleplus(array $result): array
    {
        $buckets = $result['aggregations']['per_day']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $bucket) {
            $date     = substr($bucket['key_as_string'], 0, 10);
            $incoming = 0;
            $outgoing = 0;

            foreach ($bucket['by_direction']['buckets'] ?? [] as $dir) {
                if ($dir['key'] === 'incoming') $incoming = $dir['doc_count'];
                if ($dir['key'] === 'outgoing') $outgoing = $dir['doc_count'];
            }

            $parsed[$date] = [
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
