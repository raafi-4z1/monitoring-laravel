<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('report:fetch-engine-notif')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/engine-notif-fetch.log'));

Schedule::command('report:fetch-mteleplus')
    ->dailyAt('00:02')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/mteleplus-fetch.log'));

Schedule::command('report:fetch-trx-pbi-limit')
    ->dailyAt('00:03')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/trx-pbi-limit-fetch.log'));

// then() memicu export CSV setelah fetch settlement (command terakhir) selesai
Schedule::command('report:fetch-trx-pbi-settlement')
    ->dailyAt('00:04')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-csv'))
    ->appendOutputTo(storage_path('logs/trx-pbi-settlement-fetch.log'));

Schedule::command('report:fetch-wic-metric')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wic-metric-fetch.log'));

// then() memicu export CSV setelah fetch WIC APP (command terakhir) selesai
Schedule::command('report:fetch-wic-app-metric')
    ->dailyAt('00:06')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-wic-metric-csv'))
    ->appendOutputTo(storage_path('logs/wic-app-metric-fetch.log'));

// then() memicu export CSV setelah fetch TrxPBI Loader selesai
Schedule::command('report:fetch-trx-pbi-loader')
    ->dailyAt('00:07')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-loader-csv'))
    ->appendOutputTo(storage_path('logs/trx-pbi-loader-fetch.log'));

// then() memicu export CSV setelah fetch System Online selesai
Schedule::command('report:fetch-system-online')
    ->dailyAt('00:08')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-system-online-csv'))
    ->appendOutputTo(storage_path('logs/system-online-fetch.log'));

// Job Execution (Space-X / Reporting Luar Negeri)
Schedule::command('report:fetch-spacex-ldn-job-execution')
    ->dailyAt('00:09')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/spacex-ldn-job-execution-fetch.log'));

// APP Metric (DC) (HQREPOLDNDC / Space-X Server London)
Schedule::command('report:fetch-spacex-ldn-dc-app-metric')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/spacex-ldn-dc-app-metric-fetch.log'));

// DB Metric (DC) (REPODBLDNDC / Space-X Server London)
Schedule::command('report:fetch-spacex-ldn-dc-db-metric')
    ->dailyAt('00:11')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/spacex-ldn-dc-db-metric-fetch.log'));

// File Archive (Space-X / Reporting Luar Negeri)
Schedule::command('report:fetch-spacex-ldn-file-archive')
    ->dailyAt('00:12')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/spacex-ldn-file-archive-fetch.log'));
