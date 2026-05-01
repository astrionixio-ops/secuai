<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 4 will add:
//   Schedule::command('secuai:run-scheduled-jobs')->everyMinute();
//   Schedule::command('secuai:purge-expired-invites')->daily();
//   Schedule::command('secuai:siem-flush')->everyFiveMinutes();
