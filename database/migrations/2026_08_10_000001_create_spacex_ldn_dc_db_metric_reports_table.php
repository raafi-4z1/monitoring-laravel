<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spacex_ldn_dc_db_metric_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date');
            $table->unsignedTinyInteger('trx_hour');
            $table->string('metric_type', 20);              // cpu | memory | disk
            $table->string('disk_path', 100)->default('');  // '' untuk cpu/memory
            $table->decimal('max_pct', 8, 4)->nullable();
            $table->decimal('min_pct', 8, 4)->nullable();
            $table->decimal('avg_pct', 8, 4)->nullable();
            $table->decimal('p95_pct', 8, 4)->nullable();
            $table->decimal('last_pct', 8, 4)->nullable();  // disk: last usage (0-1)
            $table->unsignedBigInteger('last_used_bytes')->nullable();
            $table->unsignedBigInteger('last_total_bytes')->nullable();
            $table->timestamps();

            $table->unique(['trx_date', 'trx_hour', 'metric_type', 'disk_path'], 'spacex_ldn_dc_db_metric_uniq');
            $table->index('trx_date', 'spacex_ldn_dc_db_metric_trx_date_idx');
            $table->index('metric_type', 'spacex_ldn_dc_db_metric_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spacex_ldn_dc_db_metric_reports');
    }
};
