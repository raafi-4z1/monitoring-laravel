<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrxPbiSettlementReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrxPbiSettlementReportService
{
    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr = $date->format('Y-m-d');

        try {
            $result = $this->es->queryTrxPbiSettlement($dateStr, $dateStr);
            $parsed = $this->es->parseTrxPbiSettlement($result);

            if (empty($parsed)) {
                Log::warning("TrxPbiSettlementReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            $saved = 0;
            foreach ($parsed as $hourStr => $rows) {
                $reportHour = Carbon::createFromFormat('Y-m-d H:i', $hourStr)
                    ->format('Y-m-d H:00:00');

                foreach ($rows as $row) {
                    TrxPbiSettlementReport::updateOrCreate(
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

            Log::info("TrxPbiSettlementReport: berhasil simpan {$saved} record untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("TrxPbiSettlementReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
