<?php

namespace App\Services;

use App\Models\BillingCustomer;
use App\Models\ProductPlan;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AbacatePayClient
{
    public function configured(): bool
    {
        return filled(config('services.abacatepay.api_key'));
    }

    /** @return array<string, mixed> */
    public function createCustomer(BillingCustomer $customer): array
    {
        return $this->post('/customers/create', array_filter([
            'email' => $customer->email,
            'name' => $customer->name,
            'taxId' => $customer->tax_id,
            'cellphone' => $customer->cellphone,
            'zipCode' => $customer->zip_code,
            'metadata' => ['team_id' => (string) $customer->team_id, 'source' => 'hub-inovaforce'],
        ], fn (mixed $value) => $value !== null && $value !== ''));
    }

    /** @return array<string, mixed> */
    public function ensureProduct(ProductPlan $plan): array
    {
        if ($plan->abacatepay_product_id) {
            return ['id' => $plan->abacatepay_product_id];
        }

        $cycle = $plan->billing_cycle === 'yearly' ? 'ANNUALLY' : 'MONTHLY';
        $data = $this->post('/products/create', [
            'externalId' => 'hub-plan-'.$plan->id,
            'name' => $plan->product->name.' — '.$plan->name,
            'description' => $plan->product->description,
            'price' => (int) round((float) $plan->price * 100),
            'currency' => 'BRL',
            'cycle' => $cycle,
        ]);

        $plan->update(['abacatepay_product_id' => $data['id']]);

        return $data;
    }

    /** @return array<string, mixed> */
    public function createSubscription(BillingCustomer $customer, ProductPlan $plan, int $subscriptionId): array
    {
        $remoteProduct = $this->ensureProduct($plan->loadMissing('product'));

        return $this->post('/subscriptions/create', [
            'items' => [['id' => $remoteProduct['id'], 'quantity' => 1]],
            'customerId' => $customer->abacatepay_customer_id,
            'methods' => ['CARD'],
            'externalId' => 'hub-subscription-'.$subscriptionId,
            'returnUrl' => route('subscriptions.index', ['current_team' => $customer->team]),
            'completionUrl' => route('subscriptions.index', ['current_team' => $customer->team, 'checkout' => 'concluido']),
            'metadata' => ['subscription_id' => (string) $subscriptionId, 'team_id' => (string) $customer->team_id],
        ]);
    }

    /** @return array<string, mixed> */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->post('/subscriptions/cancel', ['id' => $subscriptionId]);
    }

    /** @return array<string, mixed> */
    public function changePlan(string $subscriptionId, ProductPlan $plan): array
    {
        $remoteProduct = $this->ensureProduct($plan->loadMissing('product'));

        return $this->post('/subscriptions/change-plan', [
            'id' => $subscriptionId,
            'productId' => $remoteProduct['id'],
            'quantity' => 1,
        ]);
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('A integração com a AbacatePay ainda não foi configurada.');
        }

        return Http::baseUrl(config('services.abacatepay.base_url'))
            ->withToken(config('services.abacatepay.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($path, $payload);
        $body = $response->json();

        if ($response->failed() || ! ($body['success'] ?? false)) {
            throw new RuntimeException((string) ($body['error'] ?? 'Não foi possível concluir a operação na AbacatePay.'));
        }

        return $body['data'];
    }
}
