<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportSource;
use App\Models\SystemOnlineReport;
use App\Services\SystemOnlineReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportSystemOnlineCsv extends Command
{
    protected $signature = 'report:export-system-online-csv
                            {--date= : Tanggal export format Y-m-d (default: kemarin)}';

    protected $description = 'Export data System Online (response time) ke CSV untuk tanggal tertentu';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Export System Online CSV untuk: {$date->format('Y-m-d')}");

        $base = env('SYSTEM_ONLINE_EXPORT_PATH', storage_path('app/exports'));
        $dir  = $base
            . DIRECTORY_SEPARATOR . $date->format('Y')
            . DIRECTORY_SEPARATOR . $date->format('m')
            . DIRECTORY_SEPARATOR . $date->format('d');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $rows = SystemOnlineReport::with('reportSource')
            ->where('trx_date', $date->format('Y-m-d'))
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->orderBy('service_name')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada data System Online untuk tanggal ini.');

            return self::SUCCESS;
        }

        $source  = ReportSource::where('service_name', SystemOnlineReportService::SERVICE_NAME)->first();
        $prefix  = $source?->kode_prefix ?? 'SPO';
        $appId   = $source?->app_id ?? 'UNKNOWN';
        $appName = $source?->service_integrator ?? 'UNKNOWN';

        $filename = $dir . DIRECTORY_SEPARATOR
            . $date->format('Ymd') . "_{$prefix}_{$appId}_{$appName}.csv";

        (new FastExcel($this->mapRows($rows)))->configureCsv()->export($filename);

        $this->info("  {$rows->count()} baris → {$filename}");
        $this->info('Export selesai.');
        Log::channel('daily')->info('report:export-system-online-csv selesai', [
            'date'  => $date->format('Y-m-d'),
            'total' => $rows->count(),
        ]);

        return self::SUCCESS;
    }

    private function mapRows(Collection $rows): Collection
    {
        return $rows->map(fn ($item) => [
            'app_id'                => $item->reportSource?->app_id ?? '',
            'data_source'           => $item->reportSource?->data_source ?? '',
            'data_source_name'      => $item->reportSource?->data_source_name ?? '',
            'trx_date'              => $item->trx_date?->format('Y-m-d') ?? '',
            'trx_hour'              => sprintf('%02d', $item->trx_hour),
            'service_name'          => $item->service_name,
            'called_app'            => '-',
            'response_time_avg_ms'  => number_format($item->response_time_avg_ms, 1, ',', ''),
        ]);
    }
}
