<?php

namespace App\Services;

use App\Models\EngineNotifReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EngineNotifReportService
{
    public function __construct(
        protected ElasticsearchService $es
    ) {}

    // ✅ Fetch data dari ES untuk tanggal tertentu dan simpan ke DB
    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr = $date->format('Y-m-d');

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
            $avgRt = $this->es->parseAvgRtBuckets(
                $this->es->queryAvgResponseTime($dateStr, $dateStr)
            );

            $ms = $mvrkS[$dateStr]  ?? 0;
            $mf = $mvrkF[$dateStr]  ?? 0;
            $ss = $smsS[$dateStr]   ?? 0;
            $sf = $smsF[$dateStr]   ?? 0;
            $es = $emailS[$dateStr] ?? 0;
            $ef = $emailF[$dateStr] ?? 0;

            // ✅ upsert agar tidak duplikat jika dijalankan ulang
            EngineNotifReport::updateOrCreate(
                ['report_date' => $dateStr],
                [
                    'mvrk_success'      => $ms,
                    'mvrk_fail'         => $mf,
                    'sms_success'       => $ss,
                    'sms_fail'          => $sf,
                    'email_success'     => $es,
                    'email_fail'        => $ef,
                    'avg_response_time' => round($avgRt[$dateStr] ?? 0, 2),
                ]
            );

            Log::info("EngineNotifReport: berhasil simpan data {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("EngineNotifReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}