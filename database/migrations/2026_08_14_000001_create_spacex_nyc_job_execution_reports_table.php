<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spacex_nyc_job_execution_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date'); // diturunkan dari @timestamp (WIB), dipakai buat filter/sort - "per hari", bukan per jam
            $table->string('date_parameter', 8); // format asli dari log, mis. "20260804"
            $table->string('job_name', 100);
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('duration', 20)->nullable(); // format asli "HH:MM:SS"
            $table->string('filename', 150)->nullable();
            $table->string('log_type', 30)->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamps();

            $table->unique(['job_name', 'trx_date'], 'spacex_nyc_job_execution_job_date_uniq');
            $table->index('trx_date', 'spacex_nyc_job_execution_trx_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spacex_nyc_job_execution_reports');
    }
};
