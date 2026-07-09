<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\TrxPbiLimitReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrxPbiLimitReportService
{
    public const SERVICE_NAME = 'trx_pbi_limit';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "TrxPbiLimitReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL (app_id, hostname, dll. akan kosong di tampilan/export). '
                . 'Cek tabel report_sources — kemungkinan service_name berubah/terhapus.'
            );
        }

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
                            'report_source_id' => $sourceId,
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
