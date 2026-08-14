<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SpacexNycFileArchiveReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSpacexNycFileArchiveReport extends Command
{
    protected $signature = 'report:fetch-spacex-nyc-file-archive
                            {--date= : Tanggal fetch (Y-m-d). Default: kemarin}';

    protected $description = 'Fetch data File Archive server New York (Space-X / Reporting Luar Negeri) dari Elasticsearch lewat proxy Grafana';

    public function handle(SpacexNycFileArchiveReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching File Archive (New York) untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch File Archive (New York) berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch File Archive (New York) gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
