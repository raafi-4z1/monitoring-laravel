<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrxPbiLimitReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrxPbiLimitReportService
{
    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr = $date->format('Y-m-d');

        try {
            $result = $this->es->queryTrxPbiLimit($dateStr, $dateStr);
            $parsed = $this->es->parseTrxPbiLimit($result);

            if (empty($parsed)) {
                Log::warning("TrxPbiLimitReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            $saved = 0;
            foreach ($parsed as $hourStr => $rows) {
                // hourStr = "2026-07-01 07:00" → simpan sebagai "2026-07-01 07:00:00"
                $reportHour = Carbon::createFromFormat('Y-m-d H:i', $hourStr)
                    ->format('Y-m-d H:00:00');

                foreach ($rows as $row) {
                    TrxPbiLimitReport::updateOrCreate(
                        ['report_hour' => $reportHour, 'ccy2' => $row['ccy2']],
                        [
                            'total_trx'            => $row['total_trx'],
                            'total_nominal'        => $row['total_nominal'],
                            'total_nominal_eq_usd' => $row['total_nominal_eq_usd'],
                        ]
                    );
                    $saved++;
                }
            }

            Log::info("TrxPbiLimitReport: berhasil simpan {$saved} record (hourly) untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("TrxPbiLimitReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
