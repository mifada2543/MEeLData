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

/*
| Auto-arsip task yang sudah terlambat, agar daftar aktif tidak dipenuhi
| task basi. Dijalankan sekali sehari; masa tenggang diatur lewat
| config('meeldata.late_archive_grace_days') / LATE_ARCHIVE_GRACE_DAYS.
|
| Bergantung pada cron yang sama: bila cron mati, `tasks:mark-late` juga
| tidak berjalan dan tidak ada task yang menjadi `late`.
*/
Schedule::command('tasks:archive-late')
    ->dailyAt(config('meeldata.late_archive_at', '00:05'))
    ->withoutOverlapping();
