<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_aplikasi', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('master_metrik', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->unique();
            $table->string('satuan_default')->nullable();
            $table->string('keterangan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Seed metrik awal dari nilai umum metricbeat
        $now = now();
        DB::table('master_metrik')->insert([
            ['nama' => 'CPU',           'satuan_default' => '%',    'keterangan' => 'CPU Usage',              'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'MEMORY',        'satuan_default' => '%',    'keterangan' => 'Memory Usage',           'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'DISK',          'satuan_default' => '%',    'keterangan' => 'Disk Usage',             'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'NETWORK_IN',    'satuan_default' => 'MB/s', 'keterangan' => 'Network Inbound',       'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'NETWORK_OUT',   'satuan_default' => 'MB/s', 'keterangan' => 'Network Outbound',      'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'LOAD_1M',       'satuan_default' => '-',    'keterangan' => 'Load Average (1 menit)', 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'LOAD_5M',       'satuan_default' => '-',    'keterangan' => 'Load Average (5 menit)', 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'LOAD_15M',      'satuan_default' => '-',    'keterangan' => 'Load Average (15 menit)', 'created_at' => $now, 'updated_at' => $now],
            ['nama' => 'RESPONSE_TIME', 'satuan_default' => 'ms',   'keterangan' => 'Response Time',          'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('master_metrik');
        Schema::dropIfExists('master_aplikasi');
    }
};
