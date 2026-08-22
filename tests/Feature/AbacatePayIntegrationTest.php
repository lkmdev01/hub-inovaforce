<?php

namespace Tests\Feature;

use App\Models\BillingCustomer;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbacatePayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_and_sync_billing_profile(): void
    {
        config(['services.billing.provider' => 'abacatepay', 'services.abacatepay.api_key' => 'dev-key']);
        Http::fake([
            'https://api.abacatepay.com/v2/customers/create' => Http::response([
                'data' => ['id' => 'cust_123'],
                'success' => true,
                'error' => null,
            ]),
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
            'abacatepay_customer_id' => 'cust_123',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.abacatepay.com/v2/customers/create'
            && $request['taxId'] === '12.345.678/0001-90');
    }

    public function test_customer_can_start_a_subscription_checkout(): void
    {
        config(['services.billing.provider' => 'abacatepay', 'services.abacatepay.api_key' => 'dev-key']);
        Http::fake([
            'https://api.abacatepay.com/v2/subscriptions/create' => Http::response([
                'data' => ['id' => 'bill_123', 'url' => 'https://app.abacatepay.com/pay/bill_123'],
                'success' => true,
                'error' => null,
            ]),
        ]);
        $user = User::factory()->create();
        BillingCustomer::query()->create([
            'team_id' => $user->current_team_id,
            'abacatepay_customer_id' => 'cust_123',
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
            'abacatepay_product_id' => 'prod_123',
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.store', [
            'current_team' => $user->currentTeam,
            'plan' => $plan,
        ]));

        $response->assertRedirect('https://app.abacatepay.com/pay/bill_123');
        $this->assertDatabaseHas(Subscription::class, [
            'team_id' => $user->current_team_id,
            'product_plan_id' => $plan->id,
            'status' => 'pending',
            'abacatepay_checkout_id' => 'bill_123',
        ]);
        Http::assertSent(fn ($request) => $request['customerId'] === 'cust_123'
            && $request['items'][0]['id'] === 'prod_123');
    }

    public function test_signed_webhook_activates_subscription_once(): void
    {
        config([
            'services.abacatepay.webhook_secret' => 'webhook-secret',
            'services.abacatepay.webhook_public_key' => 'public-hmac-key',
        ]);
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Flow CRM', 'slug' => 'flow-crm', 'description' => 'CRM']);
        $subscription = Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Profissional',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'amount' => 179,
            'seats' => 1,
            'abacatepay_checkout_id' => 'bill_123',
        ]);
        $payload = [
            'id' => 'log_123',
            'event' => 'subscription.completed',
            'apiVersion' => 2,
            'devMode' => true,
            'data' => [
                'subscription' => ['id' => 'subs_123', 'status' => 'ACTIVE'],
                'checkout' => ['id' => 'bill_123', 'externalId' => 'hub-subscription-'.$subscription->id],
            ],
        ];
        $signature = base64_encode(hash_hmac('sha256', json_encode($payload), 'public-hmac-key', true));
        $url = route('webhooks.abacatepay', ['webhookSecret' => 'webhook-secret']);

        $this->withHeader('X-Webhook-Signature', $signature)->postJson($url, $payload)->assertOk();
        $this->withHeader('X-Webhook-Signature', $signature)->postJson($url, $payload)->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('subs_123', $subscription->abacatepay_subscription_id);
        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config([
            'services.abacatepay.webhook_secret' => 'webhook-secret',
            'services.abacatepay.webhook_public_key' => 'public-hmac-key',
        ]);

        $this->withHeader('X-Webhook-Signature', 'invalid')
            ->postJson(route('webhooks.abacatepay', ['webhookSecret' => 'webhook-secret']), [
                'id' => 'log_invalid',
                'event' => 'subscription.completed',
            ])->assertUnauthorized();
    }
}
