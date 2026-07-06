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
            $table->dateTime('report_hour');
            $table->bigInteger('mvrk_success')->default(0);
            $table->bigInteger('mvrk_fail')->default(0);
            $table->bigInteger('sms_success')->default(0);
            $table->bigInteger('sms_fail')->default(0);
            $table->bigInteger('email_success')->default(0);
            $table->bigInteger('email_fail')->default(0);
            $table->decimal('avg_response_time', 10, 2)->default(0);
            $table->decimal('avg_lifespan', 10, 2)->default(0);
            $table->timestamps();

            $table->unique('report_hour');
            $table->index('report_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_notif_reports');
    }
};
