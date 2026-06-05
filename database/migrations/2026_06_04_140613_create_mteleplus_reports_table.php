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
        Schema::create('mteleplus_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();

            $table->unsignedBigInteger('akt_success')->default(0);
            $table->unsignedBigInteger('akt_fail')->default(0);
            
            $table->unsignedBigInteger('rpin_success')->default(0);
            $table->unsignedBigInteger('rpin_fail')->default(0);
            
            $table->unsignedBigInteger('total_incoming')->default(0);
            $table->unsignedBigInteger('total_outgoing')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mteleplus_reports');
    }
};
