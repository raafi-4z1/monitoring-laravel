<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('job_execution_reports', 'spacex_ldn_job_execution_reports');

        DB::statement('ALTER TABLE `spacex_ldn_job_execution_reports` RENAME INDEX `job_execution_job_date_uniq` TO `spacex_ldn_job_execution_job_date_uniq`');
        DB::statement('ALTER TABLE `spacex_ldn_job_execution_reports` RENAME INDEX `job_execution_trx_date_idx` TO `spacex_ldn_job_execution_trx_date_idx`');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `spacex_ldn_job_execution_reports` RENAME INDEX `spacex_ldn_job_execution_job_date_uniq` TO `job_execution_job_date_uniq`');
        DB::statement('ALTER TABLE `spacex_ldn_job_execution_reports` RENAME INDEX `spacex_ldn_job_execution_trx_date_idx` TO `job_execution_trx_date_idx`');

        Schema::rename('spacex_ldn_job_execution_reports', 'job_execution_reports');
    }
};
