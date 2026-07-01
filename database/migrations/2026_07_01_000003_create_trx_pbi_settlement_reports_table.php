<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_pbi_settlement_reports', function (Blueprint $table) {
            $table->id();
            $table->dateTime('report_hour');
            $table->string('ccy2', 10);

            $table->unsignedBigInteger('total_trx')->default(0);
            $table->double('total_nominal')->default(0);
            $table->double('total_nominal_eq_usd')->default(0);

            $table->timestamps();

            $table->unique(['report_hour', 'ccy2']);
            $table->index('report_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_pbi_settlement_reports');
    }
};
