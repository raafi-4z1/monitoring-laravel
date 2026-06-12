<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed master_aplikasi dari nama_aplikasi unik yang ada di app_metrics
        $existingApps = DB::table('app_metrics')
            ->distinct()
            ->orderBy('nama_aplikasi')
            ->pluck('nama_aplikasi');

        foreach ($existingApps as $appName) {
            $nama = strtoupper(trim($appName));
            DB::table('master_aplikasi')->insertOrIgnore([
                'nama'       => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Tambah kolom FK ke app_metrics
        Schema::table('app_metrics', function (Blueprint $table): void {
            $table->unsignedBigInteger('master_aplikasi_id')->nullable()->after('satuan');
            $table->unsignedBigInteger('master_metrik_id')->nullable()->after('master_aplikasi_id');

            $table->foreign('master_aplikasi_id')
                ->references('id')->on('master_aplikasi')
                ->nullOnDelete();

            $table->foreign('master_metrik_id')
                ->references('id')->on('master_metrik')
                ->nullOnDelete();
        });

        // 3. Backfill FK dari nilai string yang sudah ada
        $aplikasiMap = DB::table('master_aplikasi')->pluck('id', 'nama');
        $metrikMap   = DB::table('master_metrik')->pluck('id', 'nama');

        DB::table('app_metrics')
            ->orderBy('id')
            ->each(function (object $row) use ($aplikasiMap, $metrikMap): void {
                DB::table('app_metrics')
                    ->where('id', $row->id)
                    ->update([
                        'master_aplikasi_id' => $aplikasiMap[$row->nama_aplikasi] ?? null,
                        'master_metrik_id'   => $metrikMap[$row->metric] ?? null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('app_metrics', function (Blueprint $table): void {
            $table->dropForeign(['master_aplikasi_id']);
            $table->dropForeign(['master_metrik_id']);
            $table->dropColumn(['master_aplikasi_id', 'master_metrik_id']);
        });
    }
};
