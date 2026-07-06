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
                // hourStr = "2026-07-01 07:00"
                $dt      = Carbon::createFromFormat('Y-m-d H:i', $hourStr);
                $trxDate = $dt->format('Y-m-d');
                $trxHour = (int) $dt->format('H');

                foreach ($rows as $row) {
                    TrxPbiLimitReport::updateOrCreate(
                        [
                            'trx_date'     => $trxDate,
                            'trx_hour'     => $trxHour,
                            'trx_currency' => $row['trx_currency'],
                        ],
                        [
                            'trx_count'    => $row['trx_count'],
                            'success_count' => $row['trx_count'],
                            'trx_amount'   => $row['trx_amount'],
                        ]
                    );
                    $saved++;
                }
            }

            Log::info("TrxPbiLimitReport: berhasil simpan {$saved} record untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("TrxPbiLimitReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
