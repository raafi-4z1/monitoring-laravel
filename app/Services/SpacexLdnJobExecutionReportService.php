<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SpacexLdnJobExecutionReport;
use App\Models\ReportSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SpacexLdnJobExecutionReportService
{
    public const SERVICE_NAME = 'job_execution';

    /**
     * Cuma 2 job ini yang diambil dari log_category BATCH_JOB - sesuai permintaan awal,
     * job lain dalam kategori yang sama (get_f1_file.sh, get_ext_file.sh, dll.) diabaikan.
     */
    private const JOB_NAMES = ['Batch_edw.sh', 'run_edw_dblink.sh'];

    public function __construct(
        protected GrafanaElasticsearchService $grafana
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "SpacexLdnJobExecutionReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL. Cek tabel report_sources.'
            );
        }

        try {
            // trx_date mengikuti @timestamp (dikonversi ke WIB) - sama seperti tanggal yang
            // ditampilkan Grafana Explore untuk baris log ini, BUKAN date_parameter (yang
            // merepresentasikan tanggal bisnis job-nya, sering beda ±1 hari karena batch baru
            // selesai dini hari besoknya). Query dibatasi 1 hari penuh WIB lewat @timestamp,
            // jadi setiap hasil sudah pasti milik tanggal ini - tidak perlu filter tambahan.
            $response = $this->grafana->msearch(
                'reportingkcln',
                ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => 'reportingkcln-*'],
                [
                    'size'  => 500,
                    'query' => [
                        'bool' => [
                            'must'   => [
                                ['query_string' => ['query' => 'log_category:"BATCH_JOB"']],
                            ],
                            'filter' => [
                                [
                                    'range' => [
                                        // Field @timestamp di index ini strict ISO-8601 (mapping
                                        // strict_date_optional_time) - format "Y-m-d H:i:s" biasa
                                        // (dengan spasi) ditolak dengan parse_exception. Wajib "T".
                                        '@timestamp' => [
                                            'gte'       => $dateStr . 'T00:00:00',
                                            'lte'       => $dateStr . 'T23:59:59',
                                            'time_zone' => '+07:00',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'sort' => [
                        ['@timestamp' => ['order' => 'desc']],
                    ],
                ]
            );

            $rows = collect($this->grafana->hits($response))
                ->filter(fn (array $doc) => in_array($doc['job_name'] ?? null, self::JOB_NAMES, true));

            if ($rows->isEmpty()) {
                Log::warning("SpacexLdnJobExecutionReport: tidak ada data untuk {$dateStr}");

                return false;
            }

            foreach ($rows as $doc) {
                SpacexLdnJobExecutionReport::updateOrCreate(
                    [
                        'job_name' => $doc['job_name'],
                        'trx_date' => $dateStr,
                    ],
                    [
                        'report_source_id' => $sourceId,
                        'date_parameter'   => $doc['date_parameter'] ?? null,
                        'start_time'       => $doc['start_time'] ?? null,
                        'end_time'         => $doc['end_time'] ?? null,
                        'duration'         => $doc['duration'] ?? null,
                        'filename'         => $doc['filename'] ?? null,
                        'log_type'         => $doc['log_type'] ?? null,
                        'status'           => $doc['status'] ?? null,
                    ]
                );
            }

            Log::info("SpacexLdnJobExecutionReport: berhasil simpan {$rows->count()} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SpacexLdnJobExecutionReport: gagal - {$e->getMessage()}");

            return false;
        }
    }
}
