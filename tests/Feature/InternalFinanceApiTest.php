<?php

namespace Tests\Feature;

use App\Models\BillingCustomer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'integration-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.internal_api.client_id' => 'institutional-dashboard',
            'services.internal_api.secret' => $this->secret,
            'services.asaas.api_key' => 'configured-for-test',
        ]);
    }

    public function test_snapshot_rejects_unsigned_requests(): void
    {
        $this->getJson('/internal/api/v1/finance/snapshot')
            ->assertUnauthorized();
    }

    public function test_snapshot_exposes_sanitized_financial_information(): void
    {
        $team = Team::query()->create(['name' => 'Cliente Exemplo', 'slug' => 'cliente-exemplo']);
        BillingCustomer::query()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Exemplo',
            'email' => 'financeiro@cliente.test',
            'tax_id' => '12345678901',
            'billing_provider' => 'asaas',
            'external_customer_id' => 'cus_test',
            'synced_at' => now(),
        ]);
        $product = Product::query()->create([
            'name' => 'Sistema Gestão',
            'slug' => 'sistema-gestao',
            'description' => 'Produto de teste',
        ]);
        $subscription = Subscription::query()->create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'plan_name' => 'Mensal',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 250,
            'seats' => 1,
        ]);
        Invoice::query()->create([
            'team_id' => $team->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-TEST-001',
            'status' => 'paid',
            'currency' => 'BRL',
            'subtotal' => 250,
            'total' => 250,
            'issued_at' => today(),
            'due_at' => today(),
            'paid_at' => now(),
        ]);

        $this->withHeaders($this->signatureHeaders())
            ->getJson('/internal/api/v1/finance/snapshot')
            ->assertOk()
            ->assertJsonPath('provider.configured', true)
            ->assertJsonPath('metrics.customers', 1)
            ->assertJsonPath('metrics.active_subscriptions', 1)
            ->assertJsonPath('metrics.mrr', 250)
            ->assertJsonPath('metrics.paid_this_month', 250)
            ->assertJsonPath('customers.0.name', 'Cliente Exemplo')
            ->assertJsonMissing(['tax_id' => '12345678901'])
            ->assertJsonMissing(['external_customer_id' => 'cus_test']);
    }

    public function test_snapshot_rejects_expired_signatures(): void
    {
        $this->withHeaders($this->signatureHeaders(now()->subMinutes(10)->timestamp))
            ->getJson('/internal/api/v1/finance/snapshot')
            ->assertUnauthorized();
    }

    /** @return array<string, string> */
    private function signatureHeaders(?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;
        $canonical = implode("\n", [
            (string) $timestamp,
            'GET',
            '/internal/api/v1/finance/snapshot',
            hash('sha256', ''),
        ]);

        return [
            'X-Inova-Client' => 'institutional-dashboard',
            'X-Inova-Timestamp' => (string) $timestamp,
            'X-Inova-Signature' => 'sha256='.hash_hmac('sha256', $canonical, $this->secret),
        ];
    }
}
