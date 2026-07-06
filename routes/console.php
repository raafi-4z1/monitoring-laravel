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
