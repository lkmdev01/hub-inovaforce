<?php

namespace App\Services;

use App\Models\BillingCustomer;
use App\Models\ProductPlan;
use App\Models\Subscription;

class BillingProviderManager
{
    public function __construct(
        private readonly AsaasClient $asaas,
        private readonly AbacatePayClient $abacatePay,
    ) {}

    public function provider(): string
    {
        return (string) config('services.billing.provider', 'asaas');
    }

    public function configured(): bool
    {
        return $this->provider() === 'abacatepay'
            ? $this->abacatePay->configured()
            : $this->asaas->configured();
    }

    public function label(): string
    {
        return $this->provider() === 'abacatepay' ? 'AbacatePay' : 'Asaas';
    }

    /** @return array{provider: string, id: string} */
    public function syncCustomer(BillingCustomer $customer): array
    {
        if ($this->provider() === 'abacatepay') {
            $remote = $this->abacatePay->createCustomer($customer);

            return ['provider' => 'abacatepay', 'id' => (string) $remote['id']];
        }

        $remote = $this->asaas->syncCustomer($customer);

        return ['provider' => 'asaas', 'id' => (string) $remote['id']];
    }

    /** @return array{provider: string, checkout_id: string, url: string} */
    public function createSubscriptionCheckout(BillingCustomer $customer, ProductPlan $plan, Subscription $subscription): array
    {
        if ($this->provider() === 'abacatepay') {
            $checkout = $this->abacatePay->createSubscription($customer, $plan, $subscription->id);

            return [
                'provider' => 'abacatepay',
                'checkout_id' => (string) $checkout['id'],
                'url' => (string) $checkout['url'],
            ];
        }

        return ['provider' => 'asaas', ...$this->asaas->createSubscriptionCheckout($customer, $plan, $subscription)];
    }

    public function cancel(Subscription $subscription): void
    {
        $provider = $this->providerFor($subscription);

        if ($provider === 'abacatepay') {
            $remoteId = $subscription->external_subscription_id ?? $subscription->abacatepay_subscription_id;
            if ($remoteId) {
                $this->abacatePay->cancelSubscription($remoteId);
            }

            return;
        }

        if ($subscription->external_subscription_id) {
            $this->asaas->cancelSubscription($subscription->external_subscription_id);
        } elseif ($subscription->external_checkout_id) {
            $this->asaas->cancelCheckout($subscription->external_checkout_id);
        }
    }

    public function changePlan(Subscription $subscription, ProductPlan $plan): bool
    {
        $provider = $this->providerFor($subscription);
        $remoteId = $subscription->external_subscription_id ?? $subscription->abacatepay_subscription_id;

        if (! $remoteId) {
            return false;
        }

        if ($provider === 'abacatepay') {
            $this->abacatePay->changePlan($remoteId, $plan);
        } else {
            $this->asaas->changePlan($remoteId, $plan->loadMissing('product'));
        }

        return true;
    }

    private function providerFor(Subscription $subscription): string
    {
        if ($subscription->billing_provider) {
            return $subscription->billing_provider;
        }

        return ($subscription->abacatepay_subscription_id || $subscription->abacatepay_checkout_id)
            ? 'abacatepay'
            : $this->provider();
    }
}
