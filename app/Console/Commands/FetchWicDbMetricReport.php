<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WicDbMetricReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchWicDbMetricReport extends Command
{
    protected $signature = 'report:fetch-wic-metric';

    protected $description = 'Fetch metrik WIC DB (CPU, Memory, Disk) dari Elasticsearch dan simpan ke DB';

    public function handle(WicDbMetricReportService $service): int
    {
        $date = Carbon::yesterday();
        $this->info("Fetching WIC Metric untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
        } else {
            $this->warn('Tidak ada data atau gagal.');
        }

        return self::SUCCESS;
    }
}
