<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mteleplus_reports', function (Blueprint $table) {
            $table->id();
            $table->dateTime('report_hour');
            $table->bigInteger('akt_success')->default(0);
            $table->bigInteger('akt_fail')->default(0);
            $table->bigInteger('rpin_success')->default(0);
            $table->bigInteger('rpin_fail')->default(0);
            $table->bigInteger('total_incoming')->default(0);
            $table->bigInteger('total_outgoing')->default(0);
            $table->timestamps();

            $table->unique('report_hour');
            $table->index('report_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mteleplus_reports');
    }
};
