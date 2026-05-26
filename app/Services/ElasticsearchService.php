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
}