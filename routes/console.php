<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:task-deadlines --days=3')
    ->dailyAt('08:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();
