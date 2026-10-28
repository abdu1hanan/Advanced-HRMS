<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Schedule::call(function () {
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('is_marked', false)
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->daily();

Schedule::command('attendance:incomplete-reminders')
    ->timezone('Asia/Karachi')
    ->dailyAt('12:00');

