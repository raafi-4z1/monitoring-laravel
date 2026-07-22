<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'wic_db_metric_reports',
        'wic_app_metric_reports',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'p95_pct')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('p95_pct', 8, 4)->nullable()->after('avg_pct');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'p95_pct')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('p95_pct');
                });
            }
        }
    }
};
