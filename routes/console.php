<?php

use App\Jobs\SyncEmailsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Email sync minden 3 percben
Schedule::job(new SyncEmailsJob)
    ->everyThreeMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Lejárt draft feltöltések törlése naponta
Schedule::command('drafts:cleanup')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();
