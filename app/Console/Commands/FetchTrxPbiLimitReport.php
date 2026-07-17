<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TrxPbiLimitReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchTrxPbiLimitReport extends Command
{
    protected $signature = 'report:fetch-trx-pbi-limit
                            {--date= : Tanggal fetch format Y-m-d (default: kemarin)}';

    protected $description = 'Fetch TrxPBI Limit report dari Elasticsearch dan simpan ke DB';

    public function handle(TrxPbiLimitReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();
        $this->info("Fetching TrxPBI Limit report untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
        } else {
            $this->warn('Tidak ada data atau gagal.');
        }

        return self::SUCCESS;
    }
}
