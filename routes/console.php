<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('report:fetch-engine-notif')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/engine-notif-fetch.log'));

Schedule::command('report:fetch-mteleplus')
    ->dailyAt('00:07')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/mteleplus-fetch.log'));

Schedule::command('report:fetch-trx-pbi-limit')
    ->dailyAt('00:09')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/trx-pbi-limit-fetch.log'));

// then() memicu export CSV setelah fetch settlement (command terakhir) selesai
Schedule::command('report:fetch-trx-pbi-settlement')
    ->dailyAt('00:11')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-csv'))
    ->appendOutputTo(storage_path('logs/trx-pbi-settlement-fetch.log'));

Schedule::command('report:fetch-wic-metric')
    ->dailyAt('00:13')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wic-metric-fetch.log'));

// then() memicu export CSV setelah fetch WIC APP (command terakhir) selesai
Schedule::command('report:fetch-wic-app-metric')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-wic-metric-csv'))
    ->appendOutputTo(storage_path('logs/wic-app-metric-fetch.log'));

// then() memicu export CSV setelah fetch TrxPBI Loader selesai
Schedule::command('report:fetch-trx-pbi-loader')
    ->dailyAt('00:17')
    ->withoutOverlapping()
    ->then(fn () => Artisan::call('report:export-trx-pbi-loader-csv'))
    ->appendOutputTo(storage_path('logs/trx-pbi-loader-fetch.log'));
