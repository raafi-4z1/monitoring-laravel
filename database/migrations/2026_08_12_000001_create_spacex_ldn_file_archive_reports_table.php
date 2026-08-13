<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spacex_ldn_file_archive_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date'); // dari @timestamp (WIB), bukan tanggal di dalam nama file
            $table->string('filename', 150);
            $table->string('pathfile', 150)->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('timestamp', 30)->nullable(); // field "timestamp" mentah dari log, apa adanya
            $table->timestamps();

            $table->unique(['filename', 'trx_date'], 'spacex_ldn_file_archive_filename_date_uniq');
            $table->index('trx_date', 'spacex_ldn_file_archive_trx_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spacex_ldn_file_archive_reports');
    }
};
