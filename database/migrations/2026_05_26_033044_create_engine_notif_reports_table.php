<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_notif_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();

            $table->unsignedBigInteger('mvrk_success')->default(0);
            $table->unsignedBigInteger('mvrk_fail')->default(0);

            $table->unsignedBigInteger('sms_success')->default(0);
            $table->unsignedBigInteger('sms_fail')->default(0);

            $table->unsignedBigInteger('email_success')->default(0);
            $table->unsignedBigInteger('email_fail')->default(0);

            $table->decimal('avg_response_time', 10, 2)->default(0);
            $table->decimal('avg_lifespan', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_notif_reports');
    }
};