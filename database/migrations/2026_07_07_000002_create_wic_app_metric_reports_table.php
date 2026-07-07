<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wic_app_metric_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->datetime('report_hour');
            $table->string('metric_type', 20);         // cpu | memory | disk
            $table->string('disk_path', 100)->default(''); // '' untuk cpu/memory
            $table->decimal('max_pct', 8, 4)->nullable();  // cpu/memory: max (0–1)
            $table->decimal('min_pct', 8, 4)->nullable();  // cpu/memory: min (0–1)
            $table->decimal('avg_pct', 8, 4)->nullable();  // cpu/memory: avg (0–1)
            $table->decimal('last_pct', 8, 4)->nullable(); // disk: last usage (0–1)
            $table->unsignedBigInteger('last_used_bytes')->nullable();
            $table->unsignedBigInteger('last_total_bytes')->nullable();
            $table->timestamps();
            $table->unique(['report_hour', 'metric_type', 'disk_path']);
            $table->index('report_hour');
            $table->index('metric_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wic_app_metric_reports');
    }
};
