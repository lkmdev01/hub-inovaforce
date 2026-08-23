<?php

namespace App\Http\Controllers;

use App\Models\ProductPlan;
use App\Models\Team;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SubscriptionCheckoutController extends Controller
{
    public function store(Team $current_team, ProductPlan $plan, BillingProviderManager $billing): RedirectResponse
    {
        $plan->load('product');
        $customer = $current_team->billingCustomer;

        if (! $customer || blank($customer->tax_id)) {
            return redirect()->route('customer.show', ['current_team' => $current_team])
                ->with('warning', 'Complete os dados do cliente antes de contratar uma assinatura.');
        }

        if (! $billing->configured()) {
            return back()->with('error', 'Adicione ASAAS_API_KEY ao ambiente para iniciar checkouts.');
        }

        if ($customer->billing_provider !== $billing->provider() || ! $customer->external_customer_id) {
            try {
                $remoteCustomer = $billing->syncCustomer($customer);
                $customer->update([
                    'billing_provider' => $remoteCustomer['provider'],
                    'external_customer_id' => $remoteCustomer['id'],
                    'synced_at' => now(),
                ]);
            } catch (RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $existing = $current_team->subscriptions()
            ->where('product_id', $plan->product_id)
            ->whereIn('status', ['pending', 'active', 'trialing'])
            ->first();

        if ($existing) {
            return redirect()->route('subscriptions.index', ['current_team' => $current_team])
                ->with('warning', 'Já existe uma assinatura ou checkout pendente para esse produto.');
        }

        $subscription = $current_team->subscriptions()->create([
            'product_id' => $plan->product_id,
            'product_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'pending',
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->price,
            'seats' => 1,
            'billing_provider' => $billing->provider(),
        ]);

        try {
            $checkout = $billing->createSubscriptionCheckout($customer->load('team'), $plan, $subscription);
            $subscription->update([
                'billing_provider' => $checkout['provider'],
                'external_checkout_id' => $checkout['checkout_id'],
                'checkout_url' => $checkout['url'],
            ]);
        } catch (RuntimeException $exception) {
            $subscription->delete();

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($checkout['url']);
    }
}
