<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mteleplus_reports', 'report_source_id')) {
            Schema::table('mteleplus_reports', function (Blueprint $table) {
                $table->foreignId('report_source_id')->nullable()->after('id')
                    ->constrained('report_sources')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mteleplus_reports', 'report_source_id')) {
            Schema::table('mteleplus_reports', function (Blueprint $table) {
                $table->dropForeign(['report_source_id']);
                $table->dropColumn('report_source_id');
            });
        }
    }
};
