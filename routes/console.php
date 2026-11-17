<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Naka-save ang Daily Logbook tuwing hatinggabi (awtomatiko rin pag binuksan ang system)
\Illuminate\Support\Facades\Schedule::command('logbook:save')->dailyAt('00:05')->timezone(config('dg.timezone', 'Asia/Manila'));

// Email digest ng benta: 10:00 PM (sinusuri bawat 10 minuto para hindi ma-miss)
\Illuminate\Support\Facades\Schedule::command('drugmart:send-digest')->everyTenMinutes();
