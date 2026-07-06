<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_pbi_limit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date');
            $table->tinyInteger('trx_hour')->unsigned();
            $table->string('trx_currency', 10);
            $table->unsignedBigInteger('trx_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->decimal('trx_amount', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['trx_date', 'trx_hour', 'trx_currency']);
            $table->index('trx_date');
            $table->index('trx_hour');
        });

        Schema::create('trx_pbi_settlement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_source_id')->nullable()->constrained('report_sources')->nullOnDelete();
            $table->date('trx_date');
            $table->tinyInteger('trx_hour')->unsigned();
            $table->string('trx_currency', 10);
            $table->unsignedBigInteger('trx_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->decimal('trx_amount', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['trx_date', 'trx_hour', 'trx_currency']);
            $table->index('trx_date');
            $table->index('trx_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_pbi_limit_reports');
        Schema::dropIfExists('trx_pbi_settlement_reports');
    }
};
