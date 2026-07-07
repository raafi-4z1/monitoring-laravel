<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\WicDbMetricReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WicDbMetricReportService
{
    public const HOST_IP      = '192.168.63.30';
    public const HOST_NAME    = 'WICADBDC';
    public const SERVICE_NAME = 'wic_db_dc';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        try {
            $cpuData  = $this->es->parseWicMetricCpuMemory(
                $this->es->queryWicMetricCpu(self::HOST_IP, $dateStr, $dateStr, self::HOST_NAME)
            );
            $memData  = $this->es->parseWicMetricCpuMemory(
                $this->es->queryWicMetricMemory(self::HOST_IP, $dateStr, $dateStr, self::HOST_NAME)
            );
            $diskData = $this->es->parseWicMetricDisk(
                $this->es->queryWicMetricDisk(self::HOST_IP, $dateStr, $dateStr, self::HOST_NAME)
            );

            $count = 0;

            foreach ($cpuData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                WicDbMetricReport::updateOrCreate(
                    ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'cpu', 'disk_path' => ''],
                    ['report_source_id' => $sourceId, 'max_pct' => $v['max_pct'], 'min_pct' => $v['min_pct'], 'avg_pct' => $v['avg_pct']]
                );
                $count++;
            }

            foreach ($memData as $hourKey => $v) {
                [$dateStr2, $hourInt] = explode(' ', $hourKey);
                WicDbMetricReport::updateOrCreate(
                    ['trx_date' => $dateStr2, 'trx_hour' => (int) $hourInt, 'metric_type' => 'memory', 'disk_path' => ''],
                    ['report_source_id' => $sourceId, 'max_pct' => $v['max_pct'], 'min_pct' => $v['min_pct'], 'avg_pct' => $v['avg_pct']]
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
