<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SpacexNycDcAppMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSpacexNycDcAppMetricReport extends Command
{
    protected $signature = 'report:fetch-spacex-nyc-dc-app-metric
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data APP Metric (DC) (HQREPONYADC / Space-X Server New York) dari Elasticsearch lewat proxy Grafana';

    public function handle(SpacexNycDcAppMetricReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching APP Metric (DC) (HQREPONYADC) untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch APP Metric (DC) (HQREPONYADC) berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch APP Metric (DC) (HQREPONYADC) gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
