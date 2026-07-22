<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\WicDbMetricReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WicDbMetricReportService
{
    public const SERVICE_NAME = 'wic_db_dc';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr      = $date->format('Y-m-d');
        $reportSource = ReportSource::where('service_name', self::SERVICE_NAME)->first();

        if ($reportSource === null || (blank($reportSource->host_ip) && blank($reportSource->service_integrator))) {
            Log::channel('daily')->warning(
                "WicDbMetricReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' "
                . 'tidak ditemukan, atau host_ip & Service Integrator (hostname) dua-duanya kosong. '
                . 'Fetch dibatalkan — isi salah satunya di menu Report Sources.'
            );

            return false;
        }

        $sourceId = $reportSource->id;
        $hostIp   = (string) ($reportSource->host_ip ?? '');
        $hostName = (string) ($reportSource->service_integrator ?? '');

        try {
            $cpuData  = $this->es->parseWicMetricCpuMemory(
                $this->es->queryWicMetricCpu($hostIp, $dateStr, $dateStr, $hostName)
            );
            $memData  = $this->es->parseWicMetricCpuMemory(
                $this->es->queryWicMetricMemory($hostIp, $dateStr, $dateStr, $hostName)
            );
            $diskData = $this->es->parseWicMetricDisk(
                $this->es->queryWicMetricDisk($hostIp, $dateStr, $dateStr, $hostName)
            );

            $count = 0;

            foreach ($cpuData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                WicDbMetricReport::updateOrCreate(
                    ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'cpu', 'disk_path' => ''],
                    ['report_source_id' => $sourceId, 'max_pct' => $v['max_pct'], 'min_pct' => $v['min_pct'], 'avg_pct' => $v['avg_pct'], 'p95_pct' => $v['p95_pct']]
                );
                $count++;
            }

            foreach ($memData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                WicDbMetricReport::updateOrCreate(
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
                    WicDbMetricReport::updateOrCreate(
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
                Log::warning("WicDbMetricReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            Log::info("WicDbMetricReport: berhasil simpan {$count} baris untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("WicDbMetricReport: gagal - {$e->getMessage()}");
            return false;
        }
    }
}
