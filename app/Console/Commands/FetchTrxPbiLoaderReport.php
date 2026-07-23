<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\TrxPbiLoaderReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchTrxPbiLoaderReport extends Command
{
    protected $signature = 'report:fetch-trx-pbi-loader
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data batch job TrxPBI Loader dari Elasticsearch dan simpan ke DB';

    public function handle(TrxPbiLoaderReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching TrxPBI Loader untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch TrxPBI Loader berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch TrxPBI Loader gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
