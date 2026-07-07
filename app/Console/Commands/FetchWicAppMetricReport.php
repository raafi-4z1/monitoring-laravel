<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WicAppMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchWicAppMetricReport extends Command
{
    protected $signature = 'report:fetch-wic-app-metric';

    protected $description = 'Fetch metrik WIC APP (CPU, Memory, Disk) dari Elasticsearch dan simpan ke DB';

    public function handle(WicAppMetricReportService $service): int
    {
        $date = Carbon::yesterday();
        $this->info("Fetching WIC APP Metric untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
        } else {
            $this->warn('Tidak ada data atau gagal.');
        }

        return self::SUCCESS;
    }
}
