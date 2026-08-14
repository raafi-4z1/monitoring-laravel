<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportSource;
use App\Models\SpacexNycFileArchiveReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SpacexNycFileArchiveReportService
{
    public const SERVICE_NAME = 'file_archive_nyc';

    public function __construct(
        protected GrafanaElasticsearchService $grafana
    ) {}

    public function fetchAndStore(Carbon $date): bool
    {
        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', self::SERVICE_NAME)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "SpacexNycFileArchiveReportService: report_source dengan service_name '" . self::SERVICE_NAME . "' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL. Cek tabel report_sources.'
            );
        }

        try {
            // trx_date dari @timestamp (WIB), BUKAN dari tanggal yang tertempel di nama file -
            // sama seperti File Archive server London, supaya konsisten dengan tanggal yang
            // ditampilkan Grafana Explore. server:"newyork_dc" membatasi ke Server New York saja
            // (index ini menampung banyak server: london_dc, newyork_dc, newyork, dst).
            $response = $this->grafana->msearch(
                'reportingkcln',
                ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => 'reportingkcln-*'],
                [
                    'size'  => 500,
                    'query' => [
                        'bool' => [
                            'must'   => [
                                ['query_string' => ['query' => 'log_category:"FILE_CHECK" AND server:"newyork_dc"']],
                            ],
                            'filter' => [
                                [
                                    'range' => [
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

            $rows = collect($this->grafana->hits($response));

            if ($rows->isEmpty()) {
                Log::warning("SpacexNycFileArchiveReport: tidak ada data untuk {$dateStr}");

                return false;
            }

            foreach ($rows as $doc) {
                SpacexNycFileArchiveReport::updateOrCreate(
                    [
                        'filename' => $doc['filename'],
                        'trx_date' => $dateStr,
                    ],
                    [
                        'report_source_id' => $sourceId,
                        'pathfile'         => $doc['pathfile'] ?? null,
                        'row_count'        => $doc['row_count'] ?? null,
                        'status'           => $doc['status'] ?? null,
                        'timestamp'        => $doc['timestamp'] ?? null,
                    ]
                );
            }

            Log::info("SpacexNycFileArchiveReport: berhasil simpan {$rows->count()} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SpacexNycFileArchiveReport: gagal - {$e->getMessage()}");

            return false;
        }
    }
}
