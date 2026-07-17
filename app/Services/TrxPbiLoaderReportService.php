<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\TrxPbiLoaderReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrxPbiLoaderReportService
{
    public const SERVICE_NAME = 'trx_pbi_loader';
    public const JOB_TYPE     = 'Batch';
    public const JOB_NAME     = 'BATCH-Execure_TrxLoaderPBI';

    public function __construct(
        protected ElasticsearchService $es
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "TrxPbiLoaderReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL (app_id, data_source akan kosong di tampilan/export). '
                . 'Cek tabel report_sources — kemungkinan service_name berubah/terhapus.'
            );
        }

        try {
            $parsed = $this->es->parseTrxPbiLoader(
                $this->es->queryTrxPbiLoader($dateStr, $dateStr),
                [$dateStr]
            );

            if ($parsed === []) {
                Log::warning("TrxPbiLoaderReport: tidak ada data untuk {$dateStr}");

                return false;
            }

            $count = 0;

            foreach ($parsed as $row) {
                TrxPbiLoaderReport::updateOrCreate(
                    [
                        'trx_date'   => $row['trx_date'],
                        'trx_hour'   => $row['trx_hour'],
                        'job_name'   => self::JOB_NAME,
                        'status_job' => $row['status_job'],
                    ],
                    [
                        'report_source_id'       => $sourceId,
                        'job_type'               => self::JOB_TYPE,
                        'start_time'             => $row['start_time'],
                        'end_time'               => $row['end_time'],
                        'duration_sec'           => $row['duration_sec'],
                        'record_processed'       => $row['record_processed'],
                        'throughput_row_per_sec' => $row['throughput_row_per_sec'],
                    ]
                );
                $count++;
            }

            Log::info("TrxPbiLoaderReport: berhasil simpan {$count} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("TrxPbiLoaderReport: gagal - {$e->getMessage()}");

            return false;
        }
    }
}
