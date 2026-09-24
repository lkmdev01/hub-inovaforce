<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AutomationAlert;
use App\Models\BillingCustomer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\SystemRun;
use App\Services\BillingProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class FinanceSnapshotController extends Controller
{
    public function __invoke(BillingProviderManager $billing): JsonResponse
    {
        $activeSubscriptions = Subscription::query()->whereIn('status', ['active', 'trialing']);
        $activeCount = (clone $activeSubscriptions)->count();
        $canceledThisMonth = Subscription::query()
            ->where('status', 'canceled')
            ->whereYear('canceled_at', now()->year)
            ->whereMonth('canceled_at', now()->month)
            ->count();

        $customers = BillingCustomer::query()
            ->with(['team:id,name,slug'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (BillingCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'team' => $customer->team->name,
                'team_slug' => $customer->team->slug,
                'provider' => $customer->billing_provider,
                'synced' => $customer->synced_at !== null,
                'synced_at' => $customer->synced_at?->toIso8601String(),
                'created_at' => $customer->created_at?->toIso8601String(),
                'hub_url' => route('admin.customers.show', $customer->team),
            ]);

        $subscriptions = Subscription::query()
            ->with(['team:id,name,slug', 'product:id,name'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'customer' => $subscription->team->name,
                'product' => $subscription->product->name,
                'plan' => $subscription->plan_name,
                'status' => $subscription->status,
                'access_status' => $subscription->access_status,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => (float) $subscription->amount,
                'seats' => $subscription->seats,
                'renews_at' => $subscription->renews_at?->toIso8601String(),
                'hub_url' => route('admin.subscriptions.index'),
            ]);

        $invoices = Invoice::query()
            ->with(['team:id,name,slug'])
            ->latest('issued_at')
            ->limit(40)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->team->name,
                'kind' => $invoice->kind,
                'status' => $invoice->status,
                'total' => (float) $invoice->total,
                'issued_at' => $invoice->issued_at->toDateString(),
                'due_at' => $invoice->due_at->toDateString(),
                'paid_at' => $invoice->paid_at?->toIso8601String(),
                'description' => $invoice->description,
                'can_refund' => $invoice->billing_provider === 'asaas' && filled($invoice->external_payment_id) && $invoice->status === 'paid',
                'hub_url' => route('admin.customers.show', $invoice->team),
            ]);

        $alerts = AutomationAlert::query()
            ->with(['team:id,name'])
            ->where('status', 'open')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AutomationAlert $alert) => [
                'id' => $alert->id,
                'customer' => $alert->team?->name,
                'category' => $alert->category,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'message' => $alert->message,
                'created_at' => $alert->created_at?->toIso8601String(),
                'hub_url' => route('admin.automations.index'),
            ]);

        $runs = SystemRun::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SystemRun $run) => [
                'name' => $run->name,
                'status' => $run->status,
                'ran_at' => Carbon::parse((string) $run->ran_at)->toIso8601String(),
                'error' => $run->error_message,
            ]);

        $products = Product::query()
            ->with(['plans' => fn ($query) => $query->where('status', 'active')])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'plans' => $product->plans->map(fn (ProductPlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'billing_cycle' => $plan->billing_cycle,
                    'billing_type' => $plan->billing_type,
                    'price' => (float) $plan->price,
                    'pricing_model' => $plan->pricing_model,
                    'minimum_seats' => $plan->minimum_seats,
                    'maximum_seats' => $plan->maximum_seats,
                ])->values()->all(),
            ]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'provider' => [
                'name' => $billing->label(),
                'configured' => $billing->configured(),
            ],
            'metrics' => [
                'customers' => BillingCustomer::query()->count(),
                'active_subscriptions' => $activeCount,
                'mrr' => round((float) (clone $activeSubscriptions)->get()->sum(
                    fn (Subscription $subscription) => $subscription->monthlyEquivalentAmount()
                ), 2),
                'pending_checkouts' => Subscription::query()->where('status', 'pending')->count(),
                'past_due' => Subscription::query()->where('status', 'past_due')->count(),
                'paid_this_month' => (float) Invoice::query()->where('status', 'paid')->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('total'),
                'overdue_amount' => (float) Invoice::query()->where('status', 'overdue')->sum('total'),
                'refunded_this_month' => (float) Invoice::query()->whereIn('status', ['refunded', 'refund_pending'])->whereYear('refunded_at', now()->year)->whereMonth('refunded_at', now()->month)->sum('total'),
                'canceled_this_month' => $canceledThisMonth,
                'churn_rate' => ($activeCount + $canceledThisMonth) > 0
                    ? round(($canceledThisMonth / ($activeCount + $canceledThisMonth)) * 100, 1)
                    : 0,
                'open_alerts' => $alerts->count(),
            ],
            'customers' => $customers,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'alerts' => $alerts,
            'operations' => [
                'healthy' => $runs->where('status', '!=', 'failed')->count() === $runs->count(),
                'runs' => $runs,
                'hub_url' => route('admin.automations.index'),
            ],
            'catalog' => ['products' => $products],
            'capabilities' => [
                'create_customer', 'sync_customer', 'create_payment', 'create_subscription',
                'cancel_subscription', 'refund_invoice', 'resolve_alert',
            ],
            'links' => [
                'dashboard' => route('admin.dashboard'),
                'customers' => route('admin.customers.index'),
                'subscriptions' => route('admin.subscriptions.index'),
                'products' => route('admin.products.index'),
                'automations' => route('admin.automations.index'),
            ],
        ]);
    }
}
