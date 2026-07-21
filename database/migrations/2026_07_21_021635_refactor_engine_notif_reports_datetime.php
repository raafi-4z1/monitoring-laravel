<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE     = 'engine_notif_reports';
    private const UNIQ_NAME = 'engine_notif_trx_uniq';
    private const IDX_NAME  = 'engine_notif_trx_date_idx';

    public function up(): void
    {
        // Tambah kolom baru dulu + backfill dari report_hour (idempotent)
        if (! Schema::hasColumn(self::TABLE, 'trx_date')) {
            Schema::table(self::TABLE, function (Blueprint $t) {
                $t->date('trx_date')->default('2000-01-01')->after('id');
                $t->unsignedTinyInteger('trx_hour')->default(0)->after('trx_date');
            });

            DB::statement('UPDATE `' . self::TABLE . '` SET trx_date = DATE(report_hour), trx_hour = HOUR(report_hour)');
        }

        // Drop kolom lama + constraint-nya kalau masih ada
        if (Schema::hasColumn(self::TABLE, 'report_hour')) {
            Schema::table(self::TABLE, function (Blueprint $t) {
                $this->dropIndexIfExists($t, 'engine_notif_reports_report_hour_unique');
                $this->dropIndexIfExists($t, 'engine_notif_reports_report_hour_index');
                $t->dropColumn('report_hour');
            });
        }

        // Tambah unique + index baru kalau belum ada
        $indexes = collect(DB::select('SHOW INDEX FROM `' . self::TABLE . '`'))->pluck('Key_name')->unique();

        if (! $indexes->contains(self::UNIQ_NAME)) {
            Schema::table(self::TABLE, function (Blueprint $t) use ($indexes) {
                $t->unique(['trx_date', 'trx_hour'], self::UNIQ_NAME);

                if (! $indexes->contains(self::IDX_NAME)) {
                    $t->index('trx_date', self::IDX_NAME);
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'report_hour')) {
            Schema::table(self::TABLE, function (Blueprint $t) {
                $t->dateTime('report_hour')->default('2000-01-01 00:00:00')->after('id');
            });

            DB::statement("UPDATE `" . self::TABLE . "` SET report_hour = CONCAT(trx_date, ' ', LPAD(trx_hour, 2, '0'), ':00:00')");
        }

        $indexes = collect(DB::select('SHOW INDEX FROM `' . self::TABLE . '`'))->pluck('Key_name')->unique();

        Schema::table(self::TABLE, function (Blueprint $t) use ($indexes) {
            if ($indexes->contains(self::UNIQ_NAME)) {
                $t->dropIndex(self::UNIQ_NAME);
            }

            if ($indexes->contains(self::IDX_NAME)) {
                $t->dropIndex(self::IDX_NAME);
            }

            if (Schema::hasColumn(self::TABLE, 'trx_date')) {
                $t->dropColumn(['trx_date', 'trx_hour']);
            }

            $t->unique('report_hour');
            $t->index('report_hour');
        });
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM `' . self::TABLE . '`'))
            ->pluck('Key_name')
            ->contains($indexName);

        if ($exists) {
            $table->dropIndex($indexName);
        }
    }
};
