<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SpacexCity;
use App\Models\ReportSource;
use App\Models\SpacexLdnJobExecutionReport;
use App\Models\SpacexNycJobExecutionReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Satu service generik untuk Job Execution di semua server Space-X - query & parsing-nya
 * identik antar server (index reportingkcln-* via proxy Grafana), yang beda cuma tiga hal:
 * service_name (buat lookup ReportSource), nilai filter "server" di query_string, dan Model
 * tujuan (masing-masing kota tetap punya tabel sendiri, lihat CITIES di bawah).
 *
 * Nambah server baru = tambah satu entri di CITIES (plus Model/Migration/Resource/Command
 * terpisah seperti biasa) - TIDAK perlu bikin class service baru lagi.
 */
class SpacexJobExecutionReportService
{
    /**
     * Cuma 2 job ini yang diambil dari log_category BATCH_JOB - sama untuk semua server,
     * job lain dalam kategori yang sama (get_f1_file.sh, get_ext_file.sh, dll.) diabaikan.
     */
    private const JOB_NAMES = ['Batch_edw.sh', 'run_edw_dblink.sh'];

    /**
     * @var array<string, array{service_name: string, server: string, model: class-string<Model>}>
     */
    private const CITIES = [
        'ldn' => [
            'service_name' => 'job_execution',
            'server'       => 'london_dc',
            'model'        => SpacexLdnJobExecutionReport::class,
        ],
        'nyc' => [
            'service_name' => 'job_execution_nyc',
            'server'       => 'newyork_dc',
            'model'        => SpacexNycJobExecutionReport::class,
        ],
    ];

    public function __construct(
        protected GrafanaElasticsearchService $grafana
    ) {}

    public function fetchAndStore(Carbon $date, SpacexCity $city): bool
    {
        $config = self::CITIES[$city->value] ?? null;

        if ($config === null) {
            Log::channel('daily')->error("SpacexJobExecutionReportService: kota '{$city->value}' belum dikonfigurasi di konstanta CITIES.");

            return false;
        }

        [$serviceName, $server, $modelClass] = [$config['service_name'], $config['server'], $config['model']];

        $dateStr  = $date->format('Y-m-d');
        $sourceId = ReportSource::where('service_name', $serviceName)->value('id');

        if ($sourceId === null) {
            Log::channel('daily')->warning(
                "SpacexJobExecutionReportService [{$city->label()}]: report_source dengan service_name '{$serviceName}' tidak ditemukan. "
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
                                ['query_string' => ['query' => "log_category:\"BATCH_JOB\" AND server:\"{$server}\""]],
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
                Log::warning("SpacexJobExecutionReportService [{$city->label()}]: tidak ada data untuk {$dateStr}");

                return false;
            }

            foreach ($rows as $doc) {
                $modelClass::updateOrCreate(
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

            Log::info("SpacexJobExecutionReportService [{$city->label()}]: berhasil simpan {$rows->count()} baris untuk {$dateStr}");

            return true;
        } catch (\Throwable $e) {
            Log::error("SpacexJobExecutionReportService [{$city->label()}]: gagal - {$e->getMessage()}");

            return false;
        }
    }
}
