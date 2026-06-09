<?php

use App\Console\Commands\ExpireSubscriptions;
use App\Console\Commands\SendRenewalReminders;
use App\Jobs\PollInstanceHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll all WhatsApp instance health every 2 minutes
Schedule::job(new PollInstanceHealth)->everyTwoMinutes();

// Suspend tenants whose subscription has expired (runs at midnight)
Schedule::command(ExpireSubscriptions::class)->dailyAt('00:05');

// Send renewal reminder notifications daily at 8am
Schedule::command(SendRenewalReminders::class)->dailyAt('08:00');
