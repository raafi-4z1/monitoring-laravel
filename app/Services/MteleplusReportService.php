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

            // ✅ Sama seperti EngineNotif — ambil data untuk tanggal spesifik
            $data = $parsed[$dateStr] ?? null;

            if (!$data) {
                Log::warning("MteleplusReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            MteleplusReport::updateOrCreate(
                ['report_date' => $dateStr],
                [
                    'akt_success'    => $data['akt_success'],
                    'akt_fail'       => $data['akt_fail'],
                    'rpin_success'   => $data['rpin_success'],
                    'rpin_fail'      => $data['rpin_fail'],
                    'total_incoming' => $data['total_incoming'],
                    'total_outgoing' => $data['total_outgoing'],
                ]
            );

            Log::info("MteleplusReport: berhasil simpan data {$dateStr}");
            return true;

        } catch (\Throwable $e) {
            Log::error("MteleplusReport: gagal simpan data {$dateStr} - {$e->getMessage()}");
            return false;
        }
    }
}
