<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SystemOnlineReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchSystemOnlineReport extends Command
{
    protected $signature = 'report:fetch-system-online {--date= : Tanggal fetch format Y-m-d (default: kemarin)}';

    protected $description = 'Fetch data System Online (response time) dari Elasticsearch dan simpan ke DB';

    public function handle(SystemOnlineReportService $service): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Fetching System Online untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch System Online berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch System Online gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
