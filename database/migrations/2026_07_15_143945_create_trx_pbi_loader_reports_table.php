<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trx_pbi_loader_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->string('job_type', 30);              // Batch
            $table->string('job_name', 100);             // BATCH-Execure_TrxLoaderPBI
            $table->date('trx_date');
            $table->unsignedTinyInteger('trx_hour');     // 0-23
            $table->time('start_time')->nullable();      // rata-rata trx_time dalam grup
            $table->time('end_time')->nullable();        // rata-rata timestamp dari nama file dalam grup
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedBigInteger('record_processed')->default(0);   // sum(total_row)
            $table->decimal('throughput_row_per_sec', 12, 2)->default(0);
            $table->string('status_job', 10);            // success | failed
            $table->timestamps();

            $table->unique(['trx_date', 'trx_hour', 'job_name', 'status_job'], 'trx_pbi_loader_uniq');
            $table->index('trx_date', 'trx_pbi_loader_date_idx');
            $table->index('status_job', 'trx_pbi_loader_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_pbi_loader_reports');
    }
};
