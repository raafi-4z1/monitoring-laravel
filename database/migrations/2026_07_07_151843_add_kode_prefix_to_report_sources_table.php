<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sources', function (Blueprint $table) {
            $table->string('kode_prefix', 20)->nullable()->after('service_integrator');
        });

        // Set nilai default berdasarkan service_name
        DB::table('report_sources')
            ->where('service_name', 'like', 'trx_pbi%')
            ->update(['kode_prefix' => 'BP']);

        DB::table('report_sources')
            ->where('service_name', 'like', 'wic%')
            ->update(['kode_prefix' => 'SPI']);
    }

    public function down(): void
    {
        Schema::table('report_sources', function (Blueprint $table) {
            $table->dropColumn('kode_prefix');
        });
    }
};
