<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SpacexCity;
use App\Services\ActivityLogger;
use App\Services\SpacexDcDbMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSpacexNycDcDbMetricReport extends Command
{
    protected $signature = 'report:fetch-spacex-nyc-dc-db-metric
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data DB Metric (DC) (REPODBNYADC / Space-X Server New York) dari Elasticsearch lewat proxy Grafana';

    public function handle(SpacexDcDbMetricReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching DB Metric (DC) (REPODBNYADC) untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date, SpacexCity::Nyc);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch DB Metric (DC) (REPODBNYADC) berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch DB Metric (DC) (REPODBNYADC) gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
