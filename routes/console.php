<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flush buffered exam answers from Redis to MariaDB every 5 seconds
Schedule::job(new \App\Jobs\FlushJawabanToDbJob)->everyFiveSeconds()->withoutOverlapping();
