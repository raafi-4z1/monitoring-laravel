<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TrxPbiSettlementReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchTrxPbiSettlementReport extends Command
{
    protected $signature = 'report:fetch-trx-pbi-settlement
                            {--date= : Tanggal fetch format Y-m-d (default: kemarin)}';

    protected $description = 'Fetch TrxPBI Settlement report dari Elasticsearch dan simpan ke DB';

    public function handle(TrxPbiSettlementReportService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();
        $this->info("Fetching TrxPBI Settlement report untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
        } else {
            $this->warn('Tidak ada data atau gagal.');
        }

        return self::SUCCESS;
    }
}
