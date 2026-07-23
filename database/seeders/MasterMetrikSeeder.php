<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterMetrikSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('master_metrik')->insertOrIgnore(
            [
                ['nama' => 'CPU',           'satuan_default' => '%',    'keterangan' => 'CPU Usage',               'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'MEMORY',        'satuan_default' => '%',    'keterangan' => 'Memory Usage',            'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'DISK',          'satuan_default' => '%',    'keterangan' => 'Disk Usage',              'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'NETWORK_IN',    'satuan_default' => 'MB/s', 'keterangan' => 'Network Inbound',        'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'NETWORK_OUT',   'satuan_default' => 'MB/s', 'keterangan' => 'Network Outbound',       'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'LOAD_1M',       'satuan_default' => '-',    'keterangan' => 'Load Average (1 menit)', 'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'LOAD_5M',       'satuan_default' => '-',    'keterangan' => 'Load Average (5 menit)', 'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'LOAD_15M',      'satuan_default' => '-',    'keterangan' => 'Load Average (15 menit)','created_at' => $now, 'updated_at' => $now],
                ['nama' => 'RESPONSE_TIME', 'satuan_default' => 'ms',   'keterangan' => 'Response Time',          'created_at' => $now, 'updated_at' => $now],
            ],
        );
    }
}
