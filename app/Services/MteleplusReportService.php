<?php

namespace App\Services;

use App\Models\MteleplusReport;
use App\Models\ReportSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MteleplusReportService
{
    public const SERVICE_NAME = 'mteleplus';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "MteleplusReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL (app_id, data_source akan kosong di export). '
                . 'Cek tabel report_sources — kemungkinan service_name berubah/terhapus.'
            );
        }

        try {
            $result = $this->es->queryMteleplus($dateStr, $dateStr);
            $parsed = $this->es->parseMteleplus($result);

            if (empty($parsed)) {
                Log::warning("MteleplusReport: tidak ada data untuk {$dateStr}");
                return false;
            }

            foreach ($parsed as $hourKey => $data) {
                // hourKey = "2026-07-01 07:00"
                $dt      = Carbon::createFromFormat('Y-m-d H:i', $hourKey);
                $trxDate = $dt->format('Y-m-d');
                $trxHour = (int) $dt->format('H');

                MteleplusReport::updateOrCreate(
                    ['trx_date' => $trxDate, 'trx_hour' => $trxHour],
                    [
                        'report_source_id' => $sourceId,
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
