<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Query Elasticsearch lewat proxy datasource Grafana (BUKAN koneksi langsung ke cluster ES
 * seperti ElasticsearchService.php) - dipakai untuk index yang cuma bisa diakses lewat Grafana,
 * mis. reportingkcln (server Reporting Luar Negeri).
 *
 * Endpoint proxy Elasticsearch di Grafana cuma mengizinkan POST ke /_msearch (search biasa
 * ke /_search akan ditolak dengan "posts not allowed on proxied Elasticsearch datasource
 * except on /_msearch"), dan bodinya harus NDJSON (baris header + baris query, per pasangan),
 * diakhiri baris kosong.
 */
class GrafanaElasticsearchService
{
    protected string $host;
    protected string $port;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->host     = config('grafana.host', '');
        $this->port     = config('grafana.port', '');
        $this->username = config('grafana.username', '');
        $this->password = config('grafana.password', '');
    }

    /**
     * @param string $datasourceKey kunci di config('grafana.datasources'), mis. 'reportingkcln'
     * @param array  $header        baris pertama NDJSON, mis. ['index' => 'reportingkcln-*']
     * @param array  $query         baris kedua NDJSON - query DSL Elasticsearch biasa
     *
     * @return array response ke-0 dari "responses" (hasil query pertama di body msearch)
     */
    public function msearch(string $datasourceKey, array $header, array $query): array
    {
        $uid = config("grafana.datasources.{$datasourceKey}", '');

        if ($uid === '') {
            Log::channel('daily')->error("GrafanaElasticsearchService: UID datasource '{$datasourceKey}' belum diisi di .env.");

            return [];
        }

        $ndjson = json_encode($header) . "\n" . json_encode($query) . "\n";

        // GRAFANA_HOST bisa diisi dengan atau tanpa skema (http://<ip> atau <ip> saja) -
        // normalisasi supaya tidak dobel "http://" kalau skemanya sudah disertakan.
        $host = $this->host;

        if (! str_starts_with($host, 'http://') && ! str_starts_with($host, 'https://')) {
            $host = 'http://' . $host;
        }

        $url = rtrim($host, '/') . ":{$this->port}/api/datasources/proxy/uid/{$uid}/_msearch";

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withBody($ndjson, 'application/x-ndjson')
            ->post($url);

        $json = $response->json() ?? [];

        if (! empty($json['error']) || $response->failed()) {
            Log::channel('daily')->error("GrafanaElasticsearchService: msearch error [{$datasourceKey}]", [
                'status' => $response->status(),
                'error'  => $json['error'] ?? $response->body(),
            ]);

            return [];
        }

        return $json['responses'][0] ?? [];
    }

    /**
     * @return array<int, array{fields: array<string, mixed>}> daftar dokumen mentah (hits._source)
     */
    public function hits(array $msearchResponse): array
    {
        return array_map(
            static fn (array $hit): array => $hit['_source'] ?? [],
            $msearchResponse['hits']['hits'] ?? []
        );
    }
}
