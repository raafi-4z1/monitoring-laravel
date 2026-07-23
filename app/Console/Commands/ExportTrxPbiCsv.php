<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportSource;
use App\Models\TrxPbiLimitReport;
use App\Models\TrxPbiSettlementReport;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportTrxPbiCsv extends Command
{
    protected $signature = 'report:export-trx-pbi-csv
                            {--date= : Tanggal export format Y-m-d (default: kemarin)}';

    protected $description = 'Export data TrxPBI Limit & Settlement ke CSV untuk tanggal tertentu';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Export TrxPBI CSV untuk: {$date->format('Y-m-d')}");

        $base = env('TRX_PBI_EXPORT_PATH', storage_path('app/exports'));
        $dir  = $base . DIRECTORY_SEPARATOR . $date->format('Y') . DIRECTORY_SEPARATOR . $date->format('m') . DIRECTORY_SEPARATOR . $date->format('d');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $limitRows      = TrxPbiLimitReport::whereDate('trx_date', $date)->get();
        $settlementRows = TrxPbiSettlementReport::whereDate('trx_date', $date)->get();

        $source   = ReportSource::where('service_name', 'trx_pbi_limit')->first();
        $prefix   = $source?->kode_prefix ?? 'BP';
        $appId    = $source?->app_id ?? 'UNKNOWN';
        $appName  = $source?->service_integrator ?? 'UNKNOWN';

        $rows     = $this->mapRows($limitRows)->merge($this->mapRows($settlementRows));
        $filename = $dir . DIRECTORY_SEPARATOR . $date->format('Ymd') . "_{$prefix}_{$appId}_{$appName}.csv";

        (new FastExcel($rows))->configureCsv()->export($filename);

        $total = $limitRows->count() + $settlementRows->count();
        $this->info("  {$total} baris (limit: {$limitRows->count()}, settlement: {$settlementRows->count()}) → {$filename}");
        $this->info('Export selesai.');
        Log::channel('daily')->info('report:export-trx-pbi-csv selesai', ['date' => $date->format('Y-m-d'), 'total' => $total]);

        if ($total > 0) {
            ActivityLogger::logGuest('export_scheduled', "Scheduled export TrxPBI berhasil: {$total} baris untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d'), 'total' => $total, 'file' => $filename]);
        } else {
            ActivityLogger::logGuest('export_scheduled_empty', "Scheduled export TrxPBI: tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }

    private function mapRows(Collection $rows): Collection
    {
        return $rows->map(fn ($item) => [
            'app_id'             => $item->reportSource?->app_id ?? '',
            'data_source'        => $item->reportSource?->data_source ?? '',
            'data_source_name'   => $item->reportSource?->data_source_name ?? '',
            'trx_date'           => $item->trx_date?->format('Y-m-d') ?? '',
            'trx_hour'           => sprintf('%02d', $item->trx_hour),
            'service_name'       => $item->reportSource?->service_name ?? '',
            'service_integrator' => $item->reportSource?->service_integrator ?? '',
            'trx_currency'       => $item->trx_currency,
            'trx_amount'         => number_format((float) $item->trx_amount, 0, ',', '.'),
            'trx_count'          => $item->trx_count,
            'success_count'      => $item->success_count,
        ]);
    }
}
