<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\TrxPbiSettlementReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchTrxPbiSettlementReport extends Command
{
    protected $signature = 'report:fetch-trx-pbi-settlement';

    protected $description = 'Fetch TrxPBI Settlement report dari Elasticsearch dan simpan ke DB';

    public function handle(TrxPbiSettlementReportService $service): int
    {
        $date = Carbon::yesterday();
        $this->info("Fetching TrxPBI Settlement report untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch TrxPBI Settlement berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch TrxPBI Settlement gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
