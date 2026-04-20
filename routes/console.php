<?php

use App\Models\BackupSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Database backups (Spatie Laravel Backup)
|--------------------------------------------------------------------------
| Runs `backup:run` daily at 02:00. If frequency is `weekly`, only Sunday runs.
| `backup:clean` enforces retention from config/backup.php (30 days by default).
*/
Schedule::call(function (): void {
    $freq = BackupSetting::query()->value('frequency') ?? 'daily';
    if ($freq === 'weekly' && Carbon::now()->dayOfWeek !== Carbon::SUNDAY) {
        return;
    }

    Artisan::call('backup:run');
})->dailyAt('02:00')->name('loom-database-backup');

Schedule::command('backup:clean')->dailyAt('02:35')->name('loom-backup-clean');

/*
|--------------------------------------------------------------------------
| Rent & lease automation
|--------------------------------------------------------------------------
*/
Schedule::command('invoices:generate-monthly')->monthlyOn(1, '6:00')->name('loom-generate-monthly-invoices');

Schedule::command('invoices:send-reminders')->dailyAt('09:00')->name('loom-rent-reminders');

Schedule::command('invoices:mark-overdue')->dailyAt('00:00')->name('loom-mark-overdue-invoices');

Schedule::command('leases:expiry-warnings')->dailyAt('08:00')->name('loom-lease-expiry-warnings');
