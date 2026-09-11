<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\WicDbMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchWicDbMetricReport extends Command
{
    protected $signature = 'report:fetch-wic-metric
                            {--date= : Tanggal spesifik (Y-m-d), default kemarin}';

    protected $description = 'Fetch metrik WIC DB (CPU, Memory, Disk) dari Elasticsearch dan simpan ke DB';

    public function handle(WicDbMetricReportService $service): int
    {
        // --date WAJIB ADA di sini, walau indeks xmb-ls* cuma menyimpan ~5 hari - justru KARENA
        // jendelanya sesempit itu. Terukur: data lebih tua dari ~4 hari sudah tidak ada lagi di
        // indeks, jadi lubang yang terlambat disadari hilang permanen. Tanpa opsi ini, tidak ada
        // cara menambal tanggal tertentu tepat pada data yang paling sempit waktu penyelamatannya.
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Fetching WIC Metric untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch WIC DB Metric berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch WIC DB Metric gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
