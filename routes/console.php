<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Otomatisasi status "late" untuk task yang melewati deadline.
| Dijalankan tiap menit oleh Laravel Scheduler.
| Cron: * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('tasks:mark-late')->everyMinute();
