<?php

namespace App\Services;

use App\Models\MteleplusReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MteleplusReportService
{
    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr = $date->format('Y-m-d');

        try {
            $result = $this->es->queryMteleplus($dateStr, $dateStr);
            $parsed = $this->es->parseMteleplus($result);

            if (empty($parsed)) {
                Log::warning("MteleplusReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            foreach ($parsed as $hourKey => $data) {
                // hourKey = "2026-07-01 07:00" → simpan sebagai datetime
                $reportHour = Carbon::createFromFormat('Y-m-d H:i', $hourKey)->format('Y-m-d H:i:s');

                MteleplusReport::updateOrCreate(
                    ['report_hour' => $reportHour],
                    [
                        'akt_success'    => $data['akt_success'],
                        'akt_fail'       => $data['akt_fail'],
                        'rpin_success'   => $data['rpin_success'],
                        'rpin_fail'      => $data['rpin_fail'],
                        'total_incoming' => $data['total_incoming'],
                        'total_outgoing' => $data['total_outgoing'],
                    ]
                );
            }

            Log::info("MteleplusReport: berhasil simpan " . count($parsed) . " jam untuk {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("MteleplusReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
