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
            $table->date('report_date');
            $table->string('ccy2', 10);

            $table->unsignedBigInteger('total_trx')->default(0);
            $table->double('total_nominal')->default(0);
            $table->double('total_nominal_eq_usd')->default(0);

            $table->timestamps();

            $table->unique(['report_date', 'ccy2']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_pbi_limit_reports');
    }
};
