<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationAlert;
use App\Models\BillingCustomer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\BillingProviderManager;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(BillingProviderManager $billing): View
    {
        $activeSubscriptions = Subscription::query()->whereIn('status', ['active', 'trialing']);
        $canceledThisMonth = Subscription::query()->where('status', 'canceled')->whereYear('canceled_at', now()->year)->whereMonth('canceled_at', now()->month)->count();
        $activeCount = (clone $activeSubscriptions)->count();
        $metrics = [
            'customers' => BillingCustomer::query()->count(),
            'active_subscriptions' => $activeCount,
            'mrr' => (clone $activeSubscriptions)->get()->sum(
                fn (Subscription $subscription) => $subscription->billing_cycle === 'yearly'
                    ? (float) $subscription->amount / 12
                    : (float) $subscription->amount
            ),
            'pending_checkouts' => Subscription::query()->where('status', 'pending')->count(),
            'past_due' => Subscription::query()->where('status', 'past_due')->count(),
            'paid_this_month' => Invoice::query()->where('status', 'paid')->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('total'),
            'overdue_amount' => Invoice::query()->where('status', 'overdue')->sum('total'),
            'refunded_this_month' => Invoice::query()->whereIn('status', ['refunded', 'refund_pending'])->whereYear('refunded_at', now()->year)->whereMonth('refunded_at', now()->month)->sum('total'),
            'canceled_this_month' => $canceledThisMonth,
            'churn_rate' => ($activeCount + $canceledThisMonth) > 0 ? ($canceledThisMonth / ($activeCount + $canceledThisMonth)) * 100 : 0,
            'open_alerts' => AutomationAlert::query()->where('status', 'open')->count(),
        ];
        $recentCustomers = BillingCustomer::query()->with('team')->latest()->take(6)->get();
        $recentSubscriptions = Subscription::query()->with(['team', 'product'])->latest()->take(6)->get();
        $recentAlerts = AutomationAlert::query()->with('team')->where('status', 'open')->latest()->take(4)->get();

        return view('admin.dashboard', [
            'metrics' => $metrics,
            'recentCustomers' => $recentCustomers,
            'recentSubscriptions' => $recentSubscriptions,
            'billingProvider' => $billing->label(),
            'billingConfigured' => $billing->configured(),
            'recentAlerts' => $recentAlerts,
        ]);
    }
}
