<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportSource;
use App\Models\WicAppMetricReport;
use App\Models\WicDbMetricReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportWicMetricCsv extends Command
{
    protected $signature = 'report:export-wic-metric-csv
                            {--date= : Tanggal export (Y-m-d). Default: kemarin}';

    protected $description = 'Export WIC DB + WIC APP Metric ke satu file CSV';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Export WIC Metric CSV untuk: {$date->format('Y-m-d')}");

        $base = env('WIC_METRIC_EXPORT_PATH', storage_path('app/exports'));
        $dir  = $base
            . DIRECTORY_SEPARATOR . $date->format('Y')
            . DIRECTORY_SEPARATOR . $date->format('m')
            . DIRECTORY_SEPARATOR . $date->format('d');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $source   = ReportSource::where('service_name', 'wic_db_dc')->first();
        $prefix   = $source?->kode_prefix ?? 'SPI';
        $appId    = $source?->app_id      ?? 'UNKNOWN';
        $filename = $dir . DIRECTORY_SEPARATOR
            . $date->format('Ymd') . "_{$prefix}_{$appId}_WIC.csv";

        $dbRows  = WicDbMetricReport::with('reportSource')
            ->where('trx_date', $date->format('Y-m-d'))
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->orderBy('metric_type')
            ->orderBy('disk_path')
            ->get();

        $appRows = WicAppMetricReport::with('reportSource')
            ->where('trx_date', $date->format('Y-m-d'))
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->orderBy('metric_type')
            ->orderBy('disk_path')
            ->get();

        $rows = $this->mapRows($dbRows)->merge($this->mapRows($appRows));

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada data WIC Metric untuk tanggal ini.');
            return self::SUCCESS;
        }

        (new FastExcel($rows))->configureCsv()->export($filename);

        $this->info("CSV berhasil dibuat: {$filename} ({$rows->count()} baris)");
        return self::SUCCESS;
    }

    private function mapRows(Collection $items): Collection
    {
        return $items->map(fn($r) => [
            'app_id'              => $r->reportSource?->app_id ?? '',
            'data_source'         => $r->reportSource?->data_source ?? '',
            'data_source_name'    => $r->reportSource?->data_source_name ?? '',
            'trx_date'            => $r->trx_date?->format('Y-m-d') ?? '',
            'trx_hour'            => sprintf('%02d', $r->trx_hour),
            'hostname'            => $r->reportSource?->service_integrator ?? '',
            'role_type'           => match($r->metric_type) {
                'disk'   => 'Disk ' . $r->disk_path,
                'cpu'    => 'CPU',
                'memory' => 'Memory',
                default  => $r->metric_type,
            },
            'utilization_avg_pct' => $r->avg_pct  !== null ? number_format($r->avg_pct  * 100, 2)
                                   : ($r->last_pct !== null ? number_format($r->last_pct * 100, 2) : ''),
            'utilization_min_pct' => $r->min_pct  !== null ? number_format($r->min_pct  * 100, 2) : '',
            'utilization_max_pct' => $r->max_pct  !== null ? number_format($r->max_pct  * 100, 2) : '',
            'utilization_p95_pct' => $r->p95_pct  !== null ? number_format($r->p95_pct  * 100, 2)
                                   : ($r->avg_pct !== null ? number_format($r->avg_pct * 100, 2)
                                   : ($r->last_pct !== null ? number_format($r->last_pct * 100, 2) : '')),
        ]);
    }
}
