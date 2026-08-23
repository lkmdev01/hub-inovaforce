<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $subscriptions = Subscription::query()
            ->with(['team.billingCustomer', 'product', 'plan'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'status'));
    }

    public function cancel(Subscription $subscription, BillingProviderManager $billing): RedirectResponse
    {
        if ($subscription->status === 'canceled') {
            return back()->with('warning', 'Essa assinatura já está cancelada.');
        }

        if ($subscription->external_subscription_id || $subscription->external_checkout_id) {
            try {
                $billing->cancel($subscription);
            } catch (RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'renews_at' => null,
            'access_status' => 'revoked',
            'access_reason' => 'admin_canceled',
            'access_updated_at' => now(),
        ]);

        return back()->with('success', 'Assinatura cancelada pelo administrador.');
    }
}
