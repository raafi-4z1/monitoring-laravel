<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportSource;
use App\Models\TrxPbiLoaderReport;
use App\Services\ActivityLogger;
use App\Services\TrxPbiLoaderReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportTrxPbiLoaderCsv extends Command
{
    protected $signature = 'report:export-trx-pbi-loader-csv
                            {--date= : Tanggal export format Y-m-d (default: kemarin)}';

    protected $description = 'Export data batch job TrxPBI Loader ke CSV untuk tanggal tertentu';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Export TrxPBI Loader CSV untuk: {$date->format('Y-m-d')}");

        $base = config('exports.trx_pbi_loader');
        $dir  = $base
            . DIRECTORY_SEPARATOR . $date->format('Y')
            . DIRECTORY_SEPARATOR . $date->format('m')
            . DIRECTORY_SEPARATOR . $date->format('d');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $rows = TrxPbiLoaderReport::with('reportSource')
            ->where('trx_date', $date->format('Y-m-d'))
            ->orderBy('trx_date')
            ->orderBy('trx_hour')
            ->orderBy('status_job')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada data TrxPBI Loader untuk tanggal ini.');
            ActivityLogger::logGuest('export_scheduled_empty', "Scheduled export TrxPBI Loader: tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);

            return self::SUCCESS;
        }

        $source  = ReportSource::where('service_name', TrxPbiLoaderReportService::SERVICE_NAME)->first();
        $prefix  = $source?->kode_prefix ?? 'SPB';
        $appId   = $source?->app_id ?? 'UNKNOWN';
        $appName = $source?->service_integrator ?? 'UNKNOWN';

        $filename = $dir . DIRECTORY_SEPARATOR
            . $date->format('Ymd') . "_{$prefix}_{$appId}_{$appName}.csv";

        (new FastExcel($this->mapRows($rows)))->configureCsv()->export($filename);

        $this->info("  {$rows->count()} baris → {$filename}");
        $this->info('Export selesai.');
        Log::channel('daily')->info('report:export-trx-pbi-loader-csv selesai', [
            'date'  => $date->format('Y-m-d'),
            'total' => $rows->count(),
        ]);
        ActivityLogger::logGuest('export_scheduled', "Scheduled export TrxPBI Loader berhasil: {$rows->count()} baris untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d'), 'total' => $rows->count(), 'file' => $filename]);

        return self::SUCCESS;
    }

    private function mapRows(Collection $rows): Collection
    {
        return $rows->map(fn ($item) => [
            'app_id'                 => $item->reportSource?->app_id ?? '',
            'data_source'            => $item->reportSource?->data_source ?? '',
            'job_type'               => $item->job_type,
            'job_name'               => $item->job_name,
            'trx_date'               => $item->trx_date?->format('Y-m-d') ?? '',
            'trx_hour'               => sprintf('%02d', $item->trx_hour),
            'start_time'             => $item->start_time ?? '',
            'end_time'               => $item->end_time ?? '',
            'durations (sec)'        => $item->duration_sec,
            'record_processed'       => $item->record_processed,
            'throughput_row_per_sec' => number_format($item->throughput_row_per_sec, 2, ',', ''),
            'status_job'             => $item->status_job,
        ]);
    }
}
