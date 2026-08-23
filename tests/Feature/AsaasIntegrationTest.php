<?php

namespace Tests\Feature;

use App\Models\BillingCustomer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.api_key' => 'sandbox-key',
            'services.asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'services.asaas.checkout_url' => 'https://asaas.com/checkoutSession/show',
            'services.asaas.webhook_token' => 'webhook-token',
        ]);
    }

    public function test_customer_can_save_and_sync_billing_profile_with_asaas(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_asaas_123']),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('customer.update', ['current_team' => $user->currentTeam]), [
            'name' => 'Acme Ltda.',
            'email' => 'financeiro@acme.test',
            'tax_id' => '12.345.678/0001-90',
            'cellphone' => '(11) 99999-9999',
            'zip_code' => '01310-100',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas(BillingCustomer::class, [
            'team_id' => $user->current_team_id,
            'billing_provider' => 'asaas',
            'external_customer_id' => 'cus_asaas_123',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.asaas.com/v3/customers'
            && $request->hasHeader('access_token', 'sandbox-key')
            && $request['cpfCnpj'] === '12345678000190'
            && $request['externalReference'] === 'hub-team-'.$user->current_team_id);
    }

    public function test_customer_can_start_an_asaas_recurring_checkout(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/checkouts' => Http::response(['id' => 'checkout_asaas_123']),
        ]);
        $user = User::factory()->create();
        BillingCustomer::query()->create([
            'team_id' => $user->current_team_id,
            'billing_provider' => 'asaas',
            'external_customer_id' => 'cus_asaas_123',
            'name' => 'Acme Ltda.',
            'email' => 'financeiro@acme.test',
            'tax_id' => '12.345.678/0001-90',
            'cellphone' => '(11) 99999-9999',
        ]);
        $product = Product::query()->create(['name' => 'Flow CRM', 'slug' => 'flow-crm', 'description' => 'CRM']);
        $plan = ProductPlan::query()->create([
            'product_id' => $product->id,
            'name' => 'Profissional',
            'billing_cycle' => 'monthly',
            'price' => 179,
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.store', [
            'current_team' => $user->currentTeam,
            'plan' => $plan,
        ]));

        $response->assertRedirect('https://asaas.com/checkoutSession/show?id=checkout_asaas_123');
        $this->assertDatabaseHas(Subscription::class, [
            'team_id' => $user->current_team_id,
            'billing_provider' => 'asaas',
            'external_checkout_id' => 'checkout_asaas_123',
            'status' => 'pending',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.asaas.com/v3/checkouts'
            && $request['billingTypes'] === ['CREDIT_CARD']
            && $request['chargeTypes'] === ['RECURRENT']
            && $request['customer'] === 'cus_asaas_123'
            && $request['subscription']['cycle'] === 'MONTHLY');
    }

    public function test_signed_checkout_webhook_activates_subscription_once(): void
    {
        $subscription = $this->pendingSubscription(['external_checkout_id' => 'checkout_asaas_123']);
        $payload = [
            'id' => 'evt_checkout_paid',
            'event' => 'CHECKOUT_PAID',
            'checkout' => ['id' => 'checkout_asaas_123', 'status' => 'PAID'],
        ];

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), $payload)->assertOk();
        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), $payload)->assertOk();

        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertSame(1, WebhookEvent::query()->where('provider', 'asaas')->count());
    }

    public function test_payment_webhook_reconciles_subscription_and_invoice(): void
    {
        $subscription = $this->pendingSubscription(['external_checkout_id' => 'checkout_asaas_456']);
        $payload = [
            'id' => 'evt_payment_received',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_asaas_456',
                'subscription' => 'sub_asaas_456',
                'checkoutSession' => 'checkout_asaas_456',
                'status' => 'RECEIVED',
                'value' => 179,
                'dateCreated' => '2026-08-21',
                'dueDate' => '2026-08-21',
                'paymentDate' => '2026-08-21',
                'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_asaas_456',
            ],
        ];

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), $payload)->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('sub_asaas_456', $subscription->external_subscription_id);
        $this->assertDatabaseHas(Invoice::class, [
            'subscription_id' => $subscription->id,
            'billing_provider' => 'asaas',
            'external_payment_id' => 'pay_asaas_456',
            'status' => 'paid',
            'total' => 179,
            'payment_url' => 'https://sandbox.asaas.com/i/pay_asaas_456',
        ]);
    }

    public function test_customer_sees_asaas_payment_link_for_an_open_invoice(): void
    {
        $subscription = $this->pendingSubscription(['status' => 'active']);
        $invoice = Invoice::query()->create([
            'team_id' => $subscription->team_id,
            'subscription_id' => $subscription->id,
            'billing_provider' => 'asaas',
            'external_payment_id' => 'pay_asaas_open',
            'payment_url' => 'https://sandbox.asaas.com/i/pay_asaas_open',
            'number' => 'ASAAS-OPEN',
            'status' => 'open',
            'currency' => 'BRL',
            'subtotal' => 179,
            'total' => 179,
            'issued_at' => today(),
            'due_at' => today()->addDays(7),
        ]);
        $user = $subscription->team->members()->firstOrFail();

        $this->actingAs($user)
            ->get(route('invoices.show', ['current_team' => $subscription->team, 'invoice' => $invoice]))
            ->assertOk()
            ->assertSee('Pagar no Asaas')
            ->assertSee('https://sandbox.asaas.com/i/pay_asaas_open', false);
    }

    public function test_customer_can_cancel_an_asaas_subscription(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/subscriptions/sub_asaas_789' => Http::response(['deleted' => true, 'id' => 'sub_asaas_789']),
        ]);
        $subscription = $this->pendingSubscription([
            'status' => 'active',
            'external_subscription_id' => 'sub_asaas_789',
        ]);
        $user = $subscription->team->members()->firstOrFail();

        $this->actingAs($user)->post(route('subscriptions.toggle', [
            'current_team' => $subscription->team,
            'subscription' => $subscription,
        ]))->assertSessionHas('success');

        $this->assertSame('canceled', $subscription->refresh()->status);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api-sandbox.asaas.com/v3/subscriptions/sub_asaas_789');
    }

    public function test_webhook_with_invalid_token_is_rejected(): void
    {
        $this->withHeader('asaas-access-token', 'invalid')
            ->postJson(route('webhooks.asaas'), ['id' => 'evt_invalid', 'event' => 'CHECKOUT_PAID'])
            ->assertUnauthorized();
    }

    /** @param array<string, mixed> $overrides */
    private function pendingSubscription(array $overrides = []): Subscription
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Flow CRM '.uniqid(),
            'slug' => 'flow-crm-'.uniqid(),
            'description' => 'CRM',
        ]);

        return Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Profissional',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'amount' => 179,
            'seats' => 1,
            'billing_provider' => 'asaas',
            ...$overrides,
        ]);
    }
}
