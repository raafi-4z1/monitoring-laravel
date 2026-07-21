<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\SystemOnlineReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SystemOnlineReportService
{
    public const SERVICE_NAME = 'system_online';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "SystemOnlineReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL (app_id, data_source akan kosong di tampilan/export). '
                . 'Cek tabel report_sources — kemungkinan service_name berubah/terhapus.'
            );
        }

        try {
            $result = $this->es->querySystemOnline($dateStr, $dateStr);
            $parsed = $this->es->parseSystemOnline($result);

            if ($parsed === []) {
                Log::warning("SystemOnlineReport: tidak ada data untuk {$dateStr}");

                return false;
            }

            $count = 0;

            foreach ($parsed as $hourStr => $rows) {
                // hourStr = "2026-07-17 07:00"
                $dt      = Carbon::createFromFormat('Y-m-d H:i', $hourStr);
                $trxDate = $dt->format('Y-m-d');
                $trxHour = (int) $dt->format('H');

                foreach ($rows as $row) {
                    SystemOnlineReport::updateOrCreate(
                        [
                            'trx_date'     => $trxDate,
                            'trx_hour'     => $trxHour,
                            'service_name' => $row['service_name'],
                        ],
                        [
                            'report_source_id'      => $sourceId,
                            'response_time_avg_ms'  => $row['response_time_avg_ms'],
                        ]
                    );
                    $count++;
                }
            }

            Log::info("SystemOnlineReport: berhasil simpan {$count} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SystemOnlineReport: gagal - {$e->getMessage()}");

            return false;
        }
    }
}