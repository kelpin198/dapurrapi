<?php

use App\Console\Commands\AutoDropLowStockCommand;
use App\Console\Commands\PurgeExpiredTrashedCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('makanans:check-stock')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/check-stock.log'));
Schedule::command('payments:refund-terminal')
    ->everyTenSeconds()
    ->withoutOverlapping();
Schedule::command(PurgeExpiredTrashedCatalog::class)
    ->everyTenSeconds()
    ->withoutOverlapping();
Schedule::command(AutoDropLowStockCommand::class)->hourly();
