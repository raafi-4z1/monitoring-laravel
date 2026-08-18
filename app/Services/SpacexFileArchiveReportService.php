<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SpacexCity;
use App\Models\ReportSource;
use App\Models\SpacexLdnFileArchiveReport;
use App\Models\SpacexNycFileArchiveReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Satu service generik untuk File Archive di semua server Space-X - lihat
 * SpacexJobExecutionReportService untuk penjelasan pola CITIES (query & parsing identik
 * antar server, cuma service_name/server/model yang beda per kota).
 */
class SpacexFileArchiveReportService
{
    /**
     * @var array<string, array{service_name: string, server: string, model: class-string<Model>}>
     */
    private const CITIES = [
        'ldn' => [
            'service_name' => 'file_archive',
            'server'       => 'london_dc',
            'model'        => SpacexLdnFileArchiveReport::class,
        ],
        'nyc' => [
            'service_name' => 'file_archive_nyc',
            'server'       => 'newyork_dc',
            'model'        => SpacexNycFileArchiveReport::class,
        ],
    ];

    public function __construct(
        protected GrafanaElasticsearchService $grafana
    ) {}

    public function fetchAndStore(Carbon $date, SpacexCity $city): bool
    {
        $config = self::CITIES[$city->value] ?? null;

        if ($config === null) {
            Log::channel('daily')->error("SpacexFileArchiveReportService: kota '{$city->value}' belum dikonfigurasi di konstanta CITIES.");

            return false;
        }

        [$serviceName, $server, $modelClass] = [$config['service_name'], $config['server'], $config['model']];

        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', $serviceName)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "SpacexFileArchiveReportService [{$city->label()}]: report_source dengan service_name '{$serviceName}' tidak ditemukan. "
                . 'Data akan tersimpan dengan report_source_id NULL. Cek tabel report_sources.'
            );
        }

        try {
            // trx_date dari @timestamp (WIB), BUKAN dari tanggal yang tertempel di nama file -
            // sama seperti Job Execution, supaya konsisten dengan tanggal yang ditampilkan
            // Grafana Explore. Filter "server" membatasi ke satu kota saja (index ini menampung
            // banyak server: london_dc, newyork_dc, newyork, dst).
            $response = $this->grafana->msearch(
                'reportingkcln',
                ['search_type' => 'query_then_fetch', 'ignore_unavailable' => true, 'index' => 'reportingkcln-*'],
                [
                    'size'  => 500,
                    'query' => [
                        'bool' => [
                            'must'   => [
                                ['query_string' => ['query' => "log_category:\"FILE_CHECK\" AND server:\"{$server}\""]],
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
                Log::warning("SpacexFileArchiveReportService [{$city->label()}]: tidak ada data untuk {$dateStr}");

                return false;
            }

            foreach ($rows as $doc) {
                $modelClass::updateOrCreate(
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

            Log::info("SpacexFileArchiveReportService [{$city->label()}]: berhasil simpan {$rows->count()} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SpacexFileArchiveReportService [{$city->label()}]: gagal - {$e->getMessage()}");

            return false;
        }
    }
}
