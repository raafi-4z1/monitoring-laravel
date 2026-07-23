<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\TrxPbiLimitReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchTrxPbiLimitReport extends Command
{
    protected $signature = 'report:fetch-trx-pbi-limit';

    protected $description = 'Fetch TrxPBI Limit report dari Elasticsearch dan simpan ke DB';

    public function handle(TrxPbiLimitReportService $service): int
    {
        $date = Carbon::yesterday();
        $this->info("Fetching TrxPBI Limit report untuk: {$date->format('Y-m-d')}");

        $ok = $service->fetchAndStore($date);

        if ($ok) {
            $this->info('Berhasil disimpan.');
            ActivityLogger::logGuest('fetch_scheduled', "Scheduled fetch TrxPBI Limit berhasil untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        } else {
            $this->warn('Tidak ada data atau gagal.');
            ActivityLogger::logGuest('fetch_scheduled_failed', "Scheduled fetch TrxPBI Limit gagal/tidak ada data untuk {$date->format('Y-m-d')}", ['command' => $this->signature, 'date' => $date->format('Y-m-d')]);
        }

        return self::SUCCESS;
    }
}
