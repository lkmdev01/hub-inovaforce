<?php

use App\Models\SystemRun;
use App\Models\TeamInvitation;
use App\Services\BillingAutomationService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call(fn () => app(BillingAutomationService::class)->runDunning())
    ->dailyAt('09:00')
    ->name('billing-reminders')
    ->withoutOverlapping()
    ->description('Send billing reminders for overdue invoices');

Schedule::command('subscriptions:finalize-cancellations')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Finalize subscriptions canceled at period end');

Schedule::command('queue:work database --stop-when-empty --tries=5 --timeout=90')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->description('Process queued Hub jobs on shared hosting');

Schedule::command('asaas:reconcile')
    ->hourly()
    ->withoutOverlapping(30)
    ->description('Reconcile Asaas subscription payments');

Schedule::call(fn () => SystemRun::query()->updateOrCreate(
    ['name' => 'scheduler-heartbeat'],
    ['status' => 'ok', 'ran_at' => now(), 'details' => ['queue' => config('queue.default')], 'error_message' => null],
))->everyMinute()->name('scheduler-heartbeat')->description('Record scheduler health');

Schedule::command('hub:backup-database')
    ->dailyAt('02:30')
    ->withoutOverlapping(30)
    ->description('Create daily local database backup');
