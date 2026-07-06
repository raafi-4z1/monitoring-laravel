<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_aplikasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('master_metrik', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('satuan_default')->nullable();
            $table->string('keterangan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('app_metrics', function (Blueprint $table) {
            $table->foreignId('master_aplikasi_id')->nullable()->after('satuan')
                ->constrained('master_aplikasi')->nullOnDelete();
            $table->foreignId('master_metrik_id')->nullable()->after('master_aplikasi_id')
                ->constrained('master_metrik')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_metrics', function (Blueprint $table) {
            $table->dropForeign(['master_aplikasi_id']);
            $table->dropForeign(['master_metrik_id']);
            $table->dropColumn(['master_aplikasi_id', 'master_metrik_id']);
        });

        Schema::dropIfExists('master_metrik');
        Schema::dropIfExists('master_aplikasi');
    }
};
