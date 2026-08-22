<?php

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
