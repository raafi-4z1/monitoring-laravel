<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sources', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 50)->unique();
            $table->string('app_id', 50)->nullable();
            $table->string('data_source', 50)->default('ELK');
            $table->string('data_source_name', 100);
            $table->string('service_integrator', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sources');
    }
};
