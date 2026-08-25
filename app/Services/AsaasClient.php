<?php

namespace App\Services;

use App\Models\BillingCustomer;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasClient
{
    public function configured(): bool
    {
        return filled(config('services.asaas.api_key'));
    }

    /** @return array<string, mixed> */
    public function syncCustomer(BillingCustomer $customer): array
    {
        $customer->loadMissing('group');
        $payload = array_filter([
            'name' => $customer->name,
            'cpfCnpj' => $this->digits($customer->tax_id),
            'email' => $customer->email,
            'mobilePhone' => $this->digits($customer->cellphone),
            'postalCode' => $this->digits($customer->zip_code),
            'externalReference' => 'hub-team-'.$customer->team_id,
            'notificationDisabled' => false,
            'groupName' => $customer->group?->name,
        ], fn (mixed $value) => $value !== null && $value !== '');

        if ($customer->billing_provider === 'asaas' && $customer->external_customer_id) {
            $payload['groupName'] = $customer->group?->name;

            return $this->put('/customers/'.$customer->external_customer_id, $payload);
        }

        return $this->post('/customers', $payload);
    }

    /** @return array{checkout_id: string, url: string} */
    public function createSubscriptionCheckout(BillingCustomer $customer, ProductPlan $plan, Subscription $subscription): array
    {
        if (! $customer->external_customer_id || $customer->billing_provider !== 'asaas') {
            throw new RuntimeException('O cliente ainda não foi sincronizado com o Asaas.');
        }

        $returnUrl = route('subscriptions.index', ['current_team' => $customer->team]);
        $checkout = $this->post('/checkouts', [
            'billingTypes' => [$plan->billing_type],
            'chargeTypes' => ['RECURRENT'],
            'minutesToExpire' => 60,
            'externalReference' => 'hub-subscription-'.$subscription->id,
            'callback' => [
                'successUrl' => $returnUrl.'?checkout=concluido',
                'cancelUrl' => $returnUrl.'?checkout=cancelado',
                'expiredUrl' => $returnUrl.'?checkout=expirado',
            ],
            'items' => [[
                'name' => $plan->product->name.' — '.$plan->name,
                'description' => $plan->product->description,
                'quantity' => $plan->pricing_model === 'per_seat' ? $subscription->seats : 1,
                'value' => (float) $plan->price,
            ]],
            'customer' => $customer->external_customer_id,
            'subscription' => [
                'cycle' => strtoupper($plan->billing_cycle),
                'nextDueDate' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        $checkoutId = (string) ($checkout['id'] ?? '');
        if ($checkoutId === '') {
            throw new RuntimeException('O Asaas não retornou o identificador do checkout.');
        }

        return [
            'checkout_id' => $checkoutId,
            'url' => rtrim((string) config('services.asaas.checkout_url'), '?&').'?id='.$checkoutId,
        ];
    }

    /** @return array<string, mixed> */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->delete('/subscriptions/'.$subscriptionId);
    }

    /** @return array<string, mixed> */
    public function cancelCheckout(string $checkoutId): array
    {
        return $this->post('/checkouts/'.$checkoutId.'/cancel', []);
    }

    /** @return array<string, mixed> */
    public function changePlan(string $subscriptionId, ProductPlan $plan, int $seats): array
    {
        return $this->put('/subscriptions/'.$subscriptionId, [
            'billingType' => $plan->billing_type,
            'value' => $plan->totalForSeats($seats),
            'cycle' => strtoupper($plan->billing_cycle),
            'description' => $plan->product->name.' — '.$plan->name,
            'externalReference' => 'hub-plan-'.$plan->id,
            'updatePendingPayments' => false,
        ]);
    }

    /** @return array<string, mixed> */
    public function configureSubscriptionInvoices(string $subscriptionId, Product $product): array
    {
        return $this->post('/subscriptions/'.$subscriptionId.'/invoiceSettings', [
            'municipalServiceId' => $product->municipal_service_id,
            'municipalServiceCode' => $product->municipal_service_code,
            'municipalServiceName' => $product->municipal_service_name,
            'deductions' => (float) $product->fiscal_deductions,
            'effectiveDatePeriod' => $product->fiscal_effective_period,
            'receivedOnly' => true,
            'observations' => $product->fiscal_observations,
            'taxes' => $product->fiscal_taxes ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    public function schedulePaymentInvoice(Subscription $subscription, string $paymentId): array
    {
        $product = $subscription->product;

        return $this->post('/invoices', [
            'payment' => $paymentId,
            'serviceDescription' => $product->fiscal_service_description,
            'observations' => $product->fiscal_observations ?? 'Serviço referente à assinatura '.$subscription->plan_name.'.',
            'externalReference' => 'hub-subscription-'.$subscription->id.'-payment-'.$paymentId,
            'value' => (float) $subscription->amount,
            'deductions' => (float) $product->fiscal_deductions,
            'effectiveDate' => today()->toDateString(),
            'municipalServiceId' => $product->municipal_service_id,
            'municipalServiceCode' => $product->municipal_service_code,
            'municipalServiceName' => $product->municipal_service_name,
            'updatePayment' => false,
            'taxes' => $product->fiscal_taxes ?? [],
        ]);
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('A integração com o Asaas ainda não foi configurada.');
        }

        return Http::baseUrl((string) config('services.asaas.base_url'))
            ->withHeaders([
                'access_token' => (string) config('services.asaas.api_key'),
                'User-Agent' => (string) config('app.name', 'Hub Inovaforce').'/1.0',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 250);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->decode($this->request()->post($path, $payload));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function put(string $path, array $payload): array
    {
        return $this->decode($this->request()->put($path, $payload));
    }

    /** @return array<string, mixed> */
    private function delete(string $path): array
    {
        return $this->decode($this->request()->delete($path));
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $body = $response->json();

        if ($response->failed()) {
            $message = data_get($body, 'errors.0.description')
                ?? data_get($body, 'errors.0.code')
                ?? data_get($body, 'message')
                ?? 'Não foi possível concluir a operação no Asaas.';

            throw new RuntimeException((string) $message);
        }

        if (! is_array($body)) {
            throw new RuntimeException('O Asaas retornou uma resposta inválida.');
        }

        return $body;
    }

    private function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/\D+/', '', $value) ?: null;
    }
}
