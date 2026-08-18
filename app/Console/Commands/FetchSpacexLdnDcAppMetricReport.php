<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SpacexCity;
use App\Services\ActivityLogger;
use App\Services\SpacexDcAppMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSpacexLdnDcAppMetricReport extends Command
{
    protected $signature = 'report:fetch-spacex-ldn-dc-app-metric
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data APP Metric (DC) (HQREPOLDNDC / Space-X Server London) dari Elasticsearch lewat proxy Grafana';

    public function handle(SpacexDcAppMetricReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching APP Metric (DC) (HQREPOLDNDC) untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date, SpacexCity::Ldn);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch APP Metric (DC) (HQREPOLDNDC) berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch APP Metric (DC) (HQREPOLDNDC) gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
