<?php

namespace Database\Seeders;

use App\MoonShine\Resources\AppMetric\AppMetricResource;
use App\MoonShine\Resources\EngineNotifReport\EngineNotifReportResource;
use App\MoonShine\Resources\SpacexLdnJobExecutionReport\SpacexLdnJobExecutionReportResource;
use App\MoonShine\Resources\SpacexLdnFileArchiveReport\SpacexLdnFileArchiveReportResource;
use App\MoonShine\Resources\SpacexLdnDcAppMetricReport\SpacexLdnDcAppMetricReportResource;
use App\MoonShine\Resources\SpacexLdnDcDbMetricReport\SpacexLdnDcDbMetricReportResource;
use App\MoonShine\Resources\SpacexNycJobExecutionReport\SpacexNycJobExecutionReportResource;
use App\MoonShine\Resources\MteleplusReport\MteleplusReportResource;
use App\MoonShine\Resources\TrxPbiLimitReport\TrxPbiLimitReportResource;
use App\MoonShine\Resources\TrxPbiLoaderReport\TrxPbiLoaderReportResource;
use App\MoonShine\Resources\SystemOnlineReport\SystemOnlineReportResource;
use App\MoonShine\Resources\TrxPbiSettlementReport\TrxPbiSettlementReportResource;
use App\MoonShine\Resources\WicAppMetricReport\WicAppMetricReportResource;
use App\MoonShine\Resources\WicDbMetricReport\WicDbMetricReportResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourcePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('resource_permissions')->insertOrIgnore(
            [
                ['resource_class' => EngineNotifReportResource::class,      'label' => 'Engine Notif Report',      'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => MteleplusReportResource::class,        'label' => 'Mteleplus Report',         'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => TrxPbiLimitReportResource::class,      'label' => 'TrxPBI Limit',             'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => TrxPbiSettlementReportResource::class, 'label' => 'TrxPBI Settlement',        'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => TrxPbiLoaderReportResource::class,     'label' => 'Batch Job',                'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SystemOnlineReportResource::class,     'label' => 'System Online',            'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => AppMetricResource::class,              'label' => 'App Metric',               'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => WicDbMetricReportResource::class,      'label' => 'WIC DB Metric',            'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => WicAppMetricReportResource::class,     'label' => 'WIC APP Metric',           'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SpacexLdnJobExecutionReportResource::class, 'label' => 'Job Execution (Space-X)',  'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SpacexLdnFileArchiveReportResource::class, 'label' => 'File Archive (Space-X)', 'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SpacexLdnDcAppMetricReportResource::class, 'label' => 'APP Metric (DC) (Space-X)', 'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SpacexLdnDcDbMetricReportResource::class, 'label' => 'DB Metric (DC) (Space-X)', 'created_at' => now(), 'updated_at' => now()],
                ['resource_class' => SpacexNycJobExecutionReportResource::class, 'label' => 'Job Execution New York (Space-X)', 'created_at' => now(), 'updated_at' => now()],
            ],
        );
    }
}
