<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
        } else {
            $this->warn('Tidak ada data atau gagal.');
        }

        return self::SUCCESS;
    }
}
