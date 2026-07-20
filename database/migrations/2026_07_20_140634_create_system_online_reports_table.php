<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_online_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date');
            $table->unsignedTinyInteger('trx_hour');
            $table->string('service_name', 50);
            $table->decimal('response_time_avg_ms', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['trx_date', 'trx_hour', 'service_name'], 'system_online_uniq');
            $table->index('trx_date', 'system_online_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_online_reports');
    }
};
