<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'wic_db_metric_reports'  => ['db', 'wic_db_metric_trx_uniq',  'wic_db_metric_trx_date_idx'],
        'wic_app_metric_reports' => ['app', 'wic_app_metric_trx_uniq', 'wic_app_metric_trx_date_idx'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $tbl => [$_alias, $uniqName, $idxName]) {
            // Add columns only if not yet present (idempotent)
            if (!Schema::hasColumn($tbl, 'trx_date')) {
                Schema::table($tbl, function (Blueprint $t) {
                    $t->date('trx_date')->default('2000-01-01')->after('report_source_id');
                    $t->unsignedTinyInteger('trx_hour')->default(0)->after('trx_date');
                });
                DB::statement("UPDATE `{$tbl}` SET trx_date = DATE(report_hour), trx_hour = HOUR(report_hour)");
            }

            // Drop old report_hour column + constraints if still present
            if (Schema::hasColumn($tbl, 'report_hour')) {
                Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                    $this->dropIndexIfExists($t, $tbl, "{$tbl}_report_hour_metric_type_disk_path_unique");
                    $this->dropIndexIfExists($t, $tbl, "{$tbl}_report_hour_index");
                    $t->dropColumn('report_hour');
                });
            }

            // Add new unique + index only if not yet present
            $indexes = collect(DB::select("SHOW INDEX FROM `{$tbl}`"))->pluck('Key_name')->unique();
            if (!$indexes->contains($uniqName)) {
                Schema::table($tbl, function (Blueprint $t) use ($uniqName, $idxName, $indexes) {
                    $t->unique(['trx_date', 'trx_hour', 'metric_type', 'disk_path'], $uniqName);
                    if (!$indexes->contains($idxName)) {
                        $t->index('trx_date', $idxName);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl => [$_alias, $uniqName, $idxName]) {
            if (!Schema::hasColumn($tbl, 'report_hour')) {
                Schema::table($tbl, function (Blueprint $t) {
                    $t->datetime('report_hour')->default('2000-01-01 00:00:00')->after('report_source_id');
                });
                DB::statement("UPDATE `{$tbl}` SET report_hour = CONCAT(trx_date, ' ', LPAD(trx_hour, 2, '0'), ':00:00')");
            }

            $indexes = collect(DB::select("SHOW INDEX FROM `{$tbl}`"))->pluck('Key_name')->unique();

            Schema::table($tbl, function (Blueprint $t) use ($tbl, $uniqName, $idxName, $indexes) {
                if ($indexes->contains($uniqName)) {
                    $t->dropIndex($uniqName);
                }
                if ($indexes->contains($idxName)) {
                    $t->dropIndex($idxName);
                }
                if (Schema::hasColumn($tbl, 'trx_date')) {
                    $t->dropColumn(['trx_date', 'trx_hour']);
                }
                $t->unique(['report_hour', 'metric_type', 'disk_path']);
                $t->index('report_hour');
            });
        }
    }

    private function dropIndexIfExists(Blueprint $t, string $tbl, string $indexName): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `{$tbl}`"))
            ->pluck('Key_name')
            ->contains($indexName);

        if ($exists) {
            $t->dropIndex($indexName);
        }
    }
};
