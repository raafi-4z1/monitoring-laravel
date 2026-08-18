<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SpacexCity;
use App\Services\ActivityLogger;
use App\Services\SpacexJobExecutionReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSpacexNycJobExecutionReport extends Command
{
    protected $signature = 'report:fetch-spacex-nyc-job-execution
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data Job Execution server New York (Space-X / Reporting Luar Negeri) dari Elasticsearch lewat proxy Grafana';

    public function handle(SpacexJobExecutionReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching Job Execution (New York) untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date, SpacexCity::Nyc);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch Job Execution (New York) berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch Job Execution (New York) gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
