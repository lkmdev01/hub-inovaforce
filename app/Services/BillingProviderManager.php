<?php

namespace App\Services;

use App\Models\BillingCustomer;
use App\Models\ProductPlan;
use App\Models\Subscription;

class BillingProviderManager
{
    public function __construct(
        private readonly AsaasClient $asaas,
    ) {}

    public function provider(): string
    {
        return 'asaas';
    }

    public function configured(): bool
    {
        return $this->asaas->configured();
    }

    public function label(): string
    {
        return 'Asaas';
    }

    /** @return array{provider: string, id: string} */
    public function syncCustomer(BillingCustomer $customer): array
    {
        $remote = $this->asaas->syncCustomer($customer);

        return ['provider' => 'asaas', 'id' => (string) $remote['id']];
    }

    /** @return array{provider: string, checkout_id: string, url: string} */
    public function createSubscriptionCheckout(BillingCustomer $customer, ProductPlan $plan, Subscription $subscription): array
    {
        return ['provider' => 'asaas', ...$this->asaas->createSubscriptionCheckout($customer, $plan, $subscription)];
    }

    public function cancel(Subscription $subscription): void
    {
        if ($subscription->external_subscription_id) {
            $this->asaas->cancelSubscription($subscription->external_subscription_id);
        } elseif ($subscription->external_checkout_id) {
            $this->asaas->cancelCheckout($subscription->external_checkout_id);
        }
    }

    public function changePlan(Subscription $subscription, ProductPlan $plan, int $seats): bool
    {
        $remoteId = $subscription->external_subscription_id;

        if (! $remoteId) {
            return false;
        }

        $this->asaas->changePlan($remoteId, $plan->loadMissing('product'), $seats);

        return true;
    }
}
