<?php

namespace App\Services;

use App\Models\EngineNotifReport;
use App\Models\ReportSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EngineNotifReportService
{
    public const SERVICE_NAME = 'engine_notif';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "EngineNotifReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL (app_id, data_source akan kosong di export). '
                . 'Cek tabel report_sources — kemungkinan service_name berubah/terhapus.'
            );
        }

        try {
            [$mvrkS, $mvrkF]   = $this->es->parseStatusBuckets(
                $this->es->queryBySendingType(4, $dateStr, $dateStr)
            );
            [$smsS, $smsF]     = $this->es->parseStatusBuckets(
                $this->es->queryBySendingType(1, $dateStr, $dateStr)
            );
            [$emailS, $emailF] = $this->es->parseStatusBuckets(
                $this->es->queryBySendingType(2, $dateStr, $dateStr)
            );
            $avgRt      = $this->es->parseAvgRtBuckets(
                $this->es->queryAvgResponseTime($dateStr, $dateStr)
            );
            $avgLifespan = $this->es->parseAvgLifespanBuckets(
                $this->es->queryAvgLifespan($dateStr, $dateStr)
            );

            // Kumpulkan semua jam yang muncul di salah satu hasil query
            $allHours = collect(array_keys($mvrkS))
                ->merge(array_keys($mvrkF))
                ->merge(array_keys($smsS))
                ->merge(array_keys($smsF))
                ->merge(array_keys($emailS))
                ->merge(array_keys($emailF))
                ->merge(array_keys($avgRt))
                ->merge(array_keys($avgLifespan))
                ->unique()
                ->sort()
                ->values();

            if ($allHours->isEmpty()) {
                Log::warning("EngineNotifReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            foreach ($allHours as $hourKey) {
                // hourKey = "2026-07-01 07:00"
                $dt      = Carbon::createFromFormat('Y-m-d H:i', $hourKey);
                $trxDate = $dt->format('Y-m-d');
                $trxHour = (int) $dt->format('H');

                EngineNotifReport::updateOrCreate(
                    ['trx_date' => $trxDate, 'trx_hour' => $trxHour],
                    [
                        'report_source_id'  => $sourceId,
                        'mvrk_success'      => $mvrkS[$hourKey]      ?? 0,
                        'mvrk_fail'         => $mvrkF[$hourKey]      ?? 0,
                        'sms_success'       => $smsS[$hourKey]       ?? 0,
                        'sms_fail'          => $smsF[$hourKey]       ?? 0,
                        'email_success'     => $emailS[$hourKey]     ?? 0,
                        'email_fail'        => $emailF[$hourKey]     ?? 0,
                        'avg_response_time' => round($avgRt[$hourKey]       ?? 0, 2),
                        'avg_lifespan'      => round($avgLifespan[$hourKey] ?? 0, 2),
                    ]
                );
            }

            Log::info("EngineNotifReport: berhasil simpan {$allHours->count()} jam untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("EngineNotifReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
