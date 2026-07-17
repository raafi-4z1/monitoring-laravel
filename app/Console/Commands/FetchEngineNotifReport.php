<?php

namespace App\Console\Commands;

use App\Services\EngineNotifReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchEngineNotifReport extends Command
{
    protected $signature = 'report:fetch-engine-notif
                            {--date= : Tanggal spesifik (Y-m-d), default kemarin}';

    protected $description = 'Fetch data Engine Notif dari Elasticsearch dan simpan ke DB';

    public function handle(EngineNotifReportService $service): int
    {
        // ✅ Default: kemarin. Bisa override dengan --date
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching data untuk tanggal: {$date->format('Y-m-d')}");

        $success = $service->fetchAndStore($date);

        if ($success) {
            $this->info('✅ Data berhasil disimpan!');
            return Command::SUCCESS;
        }

        $this->error('❌ Gagal menyimpan data, cek log untuk detail.');
        return Command::FAILURE;
    }
}