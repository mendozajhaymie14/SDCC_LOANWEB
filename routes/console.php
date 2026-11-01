<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SdccBackup;
use App\Console\Commands\ReconcileBalances;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated Payment Reminder System — FR-13 & FR-14
// Runs daily at 08:00 AM
Schedule::command('reminders:send-due')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->runInBackground();

// Automated Database Backup — NFR-07, FR-18
// Runs daily at 01:00 AM, keeps last 14 backups, git-ignored
Schedule::command('sdcc:backup')
    ->dailyAt('01:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->runInBackground();

// Balance Reconciliation — nightly dry-run audit of payment state
Schedule::command('sdcc:reconcile-balances', ['--dry-run'])
    ->dailyAt('02:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->runInBackground();
