<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\MteleplusReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchMteleplusReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:fetch-mteleplus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch mTeleplus report dari Elasticsearch dan simpan ke DB';

    /**
     * Execute the console command.
     */
    public function handle(MteleplusReportService $service): int
    {
        $date = Carbon::yesterday();
        $this->info("Fetching mTeleplus report untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch Mteleplus berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch Mteleplus gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
