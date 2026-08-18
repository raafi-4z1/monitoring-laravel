<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SpacexCity;
use App\Models\ReportSource;
use App\Models\SpacexLdnDcAppMetricReport;
use App\Models\SpacexNycDcAppMetricReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Satu service generik untuk APP Metric (DC) di semua server Space-X - lihat
 * SpacexJobExecutionReportService untuk penjelasan pola CITIES. Host per kota SUDAH data-driven
 * dari tabel report_sources (host_ip / service_integrator, lihat hostFilter()), jadi yang perlu
 * dikonfigurasi per kota di sini cuma service_name (buat lookup ReportSource) dan Model tujuan.
 *
 * Sumber data: index Metricbeat ("METRICBEAT ELKHUB") diakses lewat proxy datasource Grafana,
 * BUKAN cluster ES langsung. Mapping-nya ECS standar, jadi agent.name/host.ip/
 * system.filesystem.mount_point SUDAH keyword-type (tidak perlu ".keyword"), dan metricset.name
 * difilter pakai "match" (bukan "term .keyword") - sudah diverifikasi live ke Grafana untuk host
 * London (HQREPOLDNDC) maupun New York (HQREPONYADC), keduanya berperilaku sama.
 */
class SpacexDcAppMetricReportService
{
    private const INDEX = 'metricbeat-*';

    /**
     * @var array<string, array{service_name: string, model: class-string<Model>}>
     */
    private const CITIES = [
        'ldn' => [
            'service_name' => 'app_storage_dc',
            'model'        => SpacexLdnDcAppMetricReport::class,
        ],
        'nyc' => [
            'service_name' => 'app_storage_dc_nyc',
            'model'        => SpacexNycDcAppMetricReport::class,
        ],
    ];

    public function __construct(
        protected GrafanaElasticsearchService $grafana
    ) {}

    public function fetchAndStore(Carbon $date, SpacexCity $city): bool
    {
        $config = self::CITIES[$city->value] ?? null;

        if ($config === null) {
            Log::channel('daily')->error("SpacexDcAppMetricReportService: kota '{$city->value}' belum dikonfigurasi di konstanta CITIES.");

            return false;
        }

        [$serviceName, $modelClass] = [$config['service_name'], $config['model']];

        $dateStr      = $date->format('Y-m-d');
        $reportSource = ReportSource::where('service_name', $serviceName)->first();

        if ($reportSource === null || (blank($reportSource->host_ip) && blank($reportSource->service_integrator))) {
            Log::channel('daily')->warning(
                "SpacexDcAppMetricReportService [{$city->label()}]: report_source dengan service_name '{$serviceName}' "
                . 'tidak ditemukan, atau host_ip & Service Integrator (hostname) dua-duanya kosong. '
                . 'Fetch dibatalkan — isi salah satunya di menu Report Sources.'
            );

            return false;
        }

        $sourceId = $reportSource->id;
        $hostIp   = (string) ($reportSource->host_ip ?? '');
        $hostName = (string) ($reportSource->service_integrator ?? '');

        try {
            $cpuData  = $this->parseCpuMemory($this->queryCpu($hostIp, $dateStr, $dateStr, $hostName));
            $memData  = $this->parseCpuMemory($this->queryMemory($hostIp, $dateStr, $dateStr, $hostName));
            $diskData = $this->parseDisk($this->queryDisk($hostIp, $dateStr, $dateStr, $hostName));

            $count = 0;

            foreach ($cpuData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                $modelClass::updateOrCreate(
                    ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'cpu', 'disk_path' => ''],
                    ['report_source_id' => $sourceId, 'max_pct' => $v['max_pct'], 'min_pct' => $v['min_pct'], 'avg_pct' => $v['avg_pct'], 'p95_pct' => $v['p95_pct']]
                );
                $count++;
            }

            foreach ($memData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                $modelClass::updateOrCreate(
                    ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'memory', 'disk_path' => ''],
                    ['report_source_id' => $sourceId, 'max_pct' => $v['max_pct'], 'min_pct' => $v['min_pct'], 'avg_pct' => $v['avg_pct'], 'p95_pct' => $v['p95_pct']]
                );
                $count++;
            }

            foreach ($diskData as $hourKey => $disks) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                foreach ($disks as $disk) {
                    $rawPath  = $disk['disk_path'];
                    $diskPath = rtrim(str_replace([':\\', ':/'], '', $rawPath), '/\\') ?: $rawPath;
                    $modelClass::updateOrCreate(
                        ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'disk', 'disk_path' => $diskPath],
                        [
                            'report_source_id' => $sourceId,
                            'max_pct'          => $disk['max_pct'],
                            'min_pct'          => $disk['min_pct'],
                            'avg_pct'          => $disk['avg_pct'],
                            'p95_pct'          => $disk['p95_pct'],
                            'last_pct'         => $disk['last_pct'],
                            'last_used_bytes'  => $disk['last_used_bytes'],
                            'last_total_bytes' => $disk['last_total_bytes'],
                        ]
                    );
                    $count++;
                }
            }

            if ($count === 0) {
                Log::warning("SpacexDcAppMetricReportService [{$city->label()}]: tidak ada data untuk {$dateStr}");

                return false;
            }

            Log::info("SpacexDcAppMetricReportService [{$city->label()}]: berhasil simpan {$count} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SpacexDcAppMetricReportService [{$city->label()}]: gagal - {$e->getMessage()}");

            return false;
        }
    }

    /**
     * host_ip diprioritaskan (field host.ip, type ip - exact match pakai term); kalau kosong,
     * fallback filter pakai agent.name (hostname perangkat, type keyword di ECS).
     */
    private function hostFilter(string $hostIp, string $hostHostname): array
    {
        if ($hostIp !== '') {
            return ['term' => ['host.ip' => $hostIp]];
        }

        return ['term' => ['agent.name' => $hostHostname]];
    }

    /**
     * NOT system.filesystem.mount_point : "Z:\\" - permintaan eksplisit, drive Z: adalah network
     * share terpetakan yang tidak relevan dipantau (sesuai instruksi awal, bukan disk lokal).
     * Berlaku untuk semua kota (host Windows) - tidak berdampak kalau memang tidak ada drive Z:.
     */
    private function excludeZDrive(): array
    {
        return ['term' => ['system.filesystem.mount_point' => 'Z:\\']];
    }

    private function timeRangeFilter(string $dateFrom, string $dateTo): array
    {
        return ['range' => ['@timestamp' => [
            'gte'       => $dateFrom . 'T00:00:00.000',
            'lte'       => $dateTo . 'T23:59:59.999',
            'time_zone' => '+07:00',
        ]]];
    }

    public function queryCpu(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->grafana->msearch(
            'metricbeat_elkhub',
            ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => self::INDEX],
            [
                'size'  => 0,
                'query' => ['bool' => [
                    'filter' => [
                        $this->hostFilter($hostIp, $hostHostname),
                        ['match' => ['metricset.name' => 'cpu']],
                    ],
                    'must' => [$this->timeRangeFilter($dateFrom, $dateTo)],
                ]],
                'aggs' => ['per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'max_pct' => ['max' => ['field' => 'system.cpu.total.norm.pct']],
                        'min_pct' => ['min' => ['field' => 'system.cpu.total.norm.pct']],
                        'avg_pct' => ['avg' => ['field' => 'system.cpu.total.norm.pct']],
                        'p95_pct' => ['percentiles' => ['field' => 'system.cpu.total.norm.pct', 'percents' => [95]]],
                    ],
                ]],
            ]
        );
    }

    public function queryMemory(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->grafana->msearch(
            'metricbeat_elkhub',
            ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => self::INDEX],
            [
                'size'  => 0,
                'query' => ['bool' => [
                    'filter' => [
                        $this->hostFilter($hostIp, $hostHostname),
                        ['match' => ['metricset.name' => 'memory']],
                    ],
                    'must' => [$this->timeRangeFilter($dateFrom, $dateTo)],
                ]],
                'aggs' => ['per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'max_pct' => ['max' => ['field' => 'system.memory.actual.used.pct']],
                        'min_pct' => ['min' => ['field' => 'system.memory.actual.used.pct']],
                        'avg_pct' => ['avg' => ['field' => 'system.memory.actual.used.pct']],
                        'p95_pct' => ['percentiles' => ['field' => 'system.memory.actual.used.pct', 'percents' => [95]]],
                    ],
                ]],
            ]
        );
    }

    public function queryDisk(string $hostIp, string $dateFrom, string $dateTo, string $hostHostname = ''): array
    {
        return $this->grafana->msearch(
            'metricbeat_elkhub',
            ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => self::INDEX],
            [
                'size'  => 0,
                'query' => ['bool' => [
                    'filter' => [
                        $this->hostFilter($hostIp, $hostHostname),
                        ['match' => ['metricset.name' => 'filesystem']],
                    ],
                    'must_not' => [$this->excludeZDrive()],
                    'must'     => [$this->timeRangeFilter($dateFrom, $dateTo)],
                ]],
                'aggs' => ['per_hour' => [
                    'date_histogram' => ['field' => '@timestamp', 'calendar_interval' => '1h', 'format' => 'yyyy-MM-dd HH:mm', 'time_zone' => '+07:00', 'min_doc_count' => 1],
                    'aggs' => [
                        'by_disk' => [
                            'terms' => ['field' => 'system.filesystem.mount_point', 'size' => 20],
                            'aggs'  => [
                                'last_doc' => [
                                    'top_hits' => [
                                        'size' => 1,
                                        'sort' => [['@timestamp' => ['order' => 'desc']]],
                                    ],
                                ],
                                'max_pct' => ['max' => ['field' => 'system.filesystem.used.pct']],
                                'min_pct' => ['min' => ['field' => 'system.filesystem.used.pct']],
                                'avg_pct' => ['avg' => ['field' => 'system.filesystem.used.pct']],
                                'p95_pct' => ['percentiles' => ['field' => 'system.filesystem.used.pct', 'percents' => [95]]],
                            ],
                        ],
                    ],
                ]],
            ]
        );
    }

    public function parseCpuMemory(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $b) {
            $hour   = $b['key_as_string'];
            $maxPct = $b['max_pct']['value'] ?? null;
            $minPct = $b['min_pct']['value'] ?? null;
            $avgPct = $b['avg_pct']['value'] ?? null;
            $p95Pct = $b['p95_pct']['values']['95.0'] ?? null;

            if ($maxPct === null && $minPct === null && $avgPct === null && $p95Pct === null) {
                continue;
            }

            $parsed[$hour] = [
                'max_pct' => $maxPct,
                'min_pct' => $minPct,
                'avg_pct' => $avgPct,
                'p95_pct' => $p95Pct,
            ];
        }

        return $parsed;
    }

    public function parseDisk(array $result): array
    {
        $buckets = $result['aggregations']['per_hour']['buckets'] ?? [];
        $parsed  = [];

        foreach ($buckets as $b) {
            $hour          = $b['key_as_string'];
            $parsed[$hour] = [];

            foreach ($b['by_disk']['buckets'] ?? [] as $diskBucket) {
                $hit      = $diskBucket['last_doc']['hits']['hits'][0] ?? null;
                $src      = $hit['_source'] ?? [];
                $diskPath = $diskBucket['key'];

                $lastPct        = Arr::get($src, 'system.filesystem.used.pct');
                $lastUsedBytes  = Arr::get($src, 'system.filesystem.used.bytes');
                $lastTotalBytes = Arr::get($src, 'system.filesystem.total');

                if ($lastPct === null) {
                    continue;
                }

                $parsed[$hour][] = [
                    'disk_path'        => $diskPath,
                    'max_pct'          => $diskBucket['max_pct']['value'] ?? null,
                    'min_pct'          => $diskBucket['min_pct']['value'] ?? null,
                    'avg_pct'          => $diskBucket['avg_pct']['value'] ?? null,
                    'p95_pct'          => $diskBucket['p95_pct']['values']['95.0'] ?? null,
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
}
