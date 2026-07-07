<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportSourceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('report_sources')->upsert(
            [
                [
                    'service_name'       => 'trx_pbi_limit',
                    'app_id'             => 'AFO',
                    'data_source'        => 'ELK',
                    'data_source_name'   => 'wic-trx-pbi-ceklimit*',
                    'service_integrator' => 'WIC',
                    'kode_prefix'        => 'BP',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'service_name'       => 'trx_pbi_settlement',
                    'app_id'             => 'AFO',
                    'data_source'        => 'ELK',
                    'data_source_name'   => 'log-wic-trx-pbi*',
                    'service_integrator' => 'WIC',
                    'kode_prefix'        => 'BP',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'service_name'       => 'wic_db_dc',
                    'app_id'             => 'WIC',
                    'data_source'        => 'ELK',
                    'data_source_name'   => 'xmb-ls*',
                    'service_integrator' => 'WICADBDC',
                    'kode_prefix'        => 'SPI',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'service_name'       => 'wic_app_dc',
                    'app_id'             => 'WIC',
                    'data_source'        => 'ELK',
                    'data_source_name'   => 'xmb-ls*',
                    'service_integrator' => 'HQWIC',
                    'kode_prefix'        => 'SPI',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
            ],
            ['service_name'],
            ['app_id', 'data_source', 'data_source_name', 'service_integrator', 'kode_prefix', 'updated_at']
        );
    }
}
