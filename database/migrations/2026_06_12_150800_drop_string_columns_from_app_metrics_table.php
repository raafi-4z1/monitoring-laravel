<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_metrics', function (Blueprint $table): void {
            $table->dropColumn(['nama_aplikasi', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::table('app_metrics', function (Blueprint $table): void {
            $table->string('nama_aplikasi')->after('recorded_at');
            $table->string('metric')->after('nama_aplikasi');
        });
    }
};
