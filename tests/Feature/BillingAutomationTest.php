<?php

namespace Tests\Feature;

use App\Models\AutomationAlert;
use App\Models\BillingCustomer;
use App\Models\CommunicationLog;
use App\Models\CustomerGroup;
use App\Models\FinancialEvent;
use App\Models\FiscalDocument;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.api_key' => 'sandbox-key',
            'services.asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'services.asaas.webhook_token' => 'webhook-token',
            'services.whatsapp.webhook_url' => null,
        ]);
        Notification::fake();
    }

    public function test_administrator_can_create_and_sync_a_customer_group(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers/cus_grouped' => Http::response(['id' => 'cus_grouped']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $customerUser = User::factory()->create();
        $group = CustomerGroup::query()->create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'color' => 'violet',
        ]);
        $customer = BillingCustomer::query()->create([
            'team_id' => $customerUser->current_team_id,
            'billing_provider' => 'asaas',
            'external_customer_id' => 'cus_grouped',
            'name' => 'Acme Ltda.',
            'email' => 'financeiro@acme.test',
            'tax_id' => '12345678000190',
            'cellphone' => '11999999999',
        ]);

        $this->actingAs($admin)->patch(route('admin.customers.group', $customerUser->currentTeam), [
            'customer_group_id' => $group->id,
        ])->assertSessionHas('success');

        $this->assertSame($group->id, $customer->refresh()->customer_group_id);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://api-sandbox.asaas.com/v3/customers/cus_grouped'
            && $request['groupName'] === 'Enterprise');
    }

    public function test_overdue_webhook_suspends_access_and_creates_automations(): void
    {
        $subscription = $this->subscriptionWithCustomer(['status' => 'active', 'access_status' => 'active']);
        $payload = [
            'id' => 'evt_overdue_1',
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_overdue_1',
                'subscription' => 'sub_automation_1',
                'status' => 'OVERDUE',
                'value' => 199,
                'dueDate' => today()->subDay()->toDateString(),
                'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_overdue_1',
            ],
        ];

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), $payload)->assertOk();
        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), $payload)->assertOk();

        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        $this->assertSame('suspended', $subscription->access_status);
        $this->assertDatabaseHas(Invoice::class, ['external_payment_id' => 'pay_overdue_1', 'status' => 'overdue']);
        $this->assertSame(1, AutomationAlert::query()->where('category', 'overdue')->count());
        $this->assertSame(1, FinancialEvent::query()->where('external_event_id', 'evt_overdue_1')->count());
        $this->assertDatabaseHas(CommunicationLog::class, ['channel' => 'email', 'status' => 'sent', 'template' => 'payment_overdue']);
        $this->assertDatabaseHas(CommunicationLog::class, ['channel' => 'whatsapp', 'status' => 'waiting_configuration']);
    }

    public function test_payment_webhook_releases_access_and_stores_receipt(): void
    {
        $subscription = $this->subscriptionWithCustomer(['status' => 'past_due', 'access_status' => 'suspended']);
        AutomationAlert::query()->create([
            'team_id' => $subscription->team_id,
            'subscription_id' => $subscription->id,
            'category' => 'overdue',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Inadimplente',
            'message' => 'Pagamento pendente.',
            'deduplication_key' => 'overdue-'.$subscription->id,
        ]);

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), [
            'id' => 'evt_received_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_received_1',
                'subscription' => 'sub_automation_1',
                'status' => 'RECEIVED',
                'value' => 199,
                'dueDate' => today()->toDateString(),
                'paymentDate' => today()->toDateString(),
                'transactionReceiptUrl' => 'https://sandbox.asaas.com/receipt/pay_received_1',
            ],
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $subscription->access_status);
        $this->assertDatabaseHas(Invoice::class, [
            'external_payment_id' => 'pay_received_1',
            'status' => 'paid',
            'receipt_url' => 'https://sandbox.asaas.com/receipt/pay_received_1',
        ]);
        $this->assertDatabaseHas(AutomationAlert::class, ['deduplication_key' => 'overdue-'.$subscription->id, 'status' => 'resolved']);
    }

    public function test_fiscal_configuration_and_invoice_webhook_are_synchronized(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/subscriptions/sub_automation_1/invoiceSettings' => Http::response(['id' => 'settings_1']),
        ]);
        $subscription = $this->subscriptionWithCustomer();
        $subscription->product->update([
            'fiscal_enabled' => true,
            'municipal_service_code' => '1.01',
            'municipal_service_name' => 'Análise e desenvolvimento de sistemas',
            'fiscal_service_description' => 'Licenciamento e manutenção mensal de software.',
            'fiscal_observations' => 'Serviço recorrente.',
            'fiscal_deductions' => 0,
            'fiscal_effective_period' => 'ON_PAYMENT_CONFIRMATION',
            'fiscal_taxes' => ['retainIss' => false, 'iss' => 2, 'cofins' => 0, 'csll' => 0, 'inss' => 0, 'ir' => 0, 'pis' => 0],
        ]);

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), [
            'id' => 'evt_payment_created_fiscal',
            'event' => 'PAYMENT_CREATED',
            'payment' => [
                'id' => 'pay_fiscal_1',
                'subscription' => 'sub_automation_1',
                'status' => 'PENDING',
                'value' => 199,
                'dueDate' => today()->addDays(7)->toDateString(),
            ],
        ])->assertOk();

        $this->assertNotNull($subscription->refresh()->fiscal_configured_at);
        Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.asaas.com/v3/subscriptions/sub_automation_1/invoiceSettings'
            && $request['effectiveDatePeriod'] === 'ON_PAYMENT_CONFIRMATION'
            && $request['municipalServiceCode'] === '1.01');

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), [
            'id' => 'evt_invoice_authorized_1',
            'event' => 'INVOICE_AUTHORIZED',
            'invoice' => [
                'id' => 'inv_fiscal_1',
                'payment' => 'pay_fiscal_1',
                'status' => 'AUTHORIZED',
                'number' => '20260001',
                'validationCode' => 'ABC123',
                'pdfUrl' => 'https://asaas.test/nfse.pdf',
                'xmlUrl' => 'https://asaas.test/nfse.xml',
                'value' => 199,
                'effectiveDate' => today()->toDateString(),
            ],
        ])->assertOk();

        $this->assertDatabaseHas(FiscalDocument::class, [
            'external_invoice_id' => 'inv_fiscal_1',
            'external_payment_id' => 'pay_fiscal_1',
            'status' => 'authorized',
            'number' => '20260001',
            'pdf_url' => 'https://asaas.test/nfse.pdf',
            'xml_url' => 'https://asaas.test/nfse.xml',
        ]);
    }

    public function test_dunning_is_idempotent_and_visible_in_admin_center(): void
    {
        $subscription = $this->subscriptionWithCustomer(['status' => 'past_due', 'access_status' => 'suspended']);
        Invoice::query()->create([
            'team_id' => $subscription->team_id,
            'subscription_id' => $subscription->id,
            'number' => 'ASAAS-DUNNING',
            'status' => 'overdue',
            'currency' => 'BRL',
            'subtotal' => 199,
            'total' => 199,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDays(3),
        ]);

        app(BillingAutomationService::class)->runDunning();
        app(BillingAutomationService::class)->runDunning();

        $this->assertSame(1, CommunicationLog::query()->where('template', 'overdue_d3')->where('channel', 'email')->count());
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.automations.index'))->assertOk()->assertSee('Automações e eventos')->assertSee('Comunicações');
    }

    public function test_access_change_is_sent_to_the_configured_software(): void
    {
        Http::fake(['https://crm.example.test/webhooks/access' => Http::response(['ok' => true])]);
        $subscription = $this->subscriptionWithCustomer(['status' => 'active', 'access_status' => 'active']);
        $subscription->product->update([
            'provisioning_webhook_url' => 'https://crm.example.test/webhooks/access',
            'provisioning_webhook_secret' => str_repeat('s', 32),
        ]);

        $this->withHeader('asaas-access-token', 'webhook-token')->postJson(route('webhooks.asaas'), [
            'id' => 'evt_access_suspended_1',
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_access_1',
                'subscription' => 'sub_automation_1',
                'status' => 'OVERDUE',
                'value' => 199,
                'dueDate' => today()->subDay()->toDateString(),
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://crm.example.test/webhooks/access'
            && $request->hasHeader('X-Hub-Event', 'subscription.access_updated')
            && str_starts_with($request->header('X-Hub-Signature')[0] ?? '', 'sha256=')
            && $request['subscription']['access_status'] === 'suspended');
        $this->assertDatabaseHas(CommunicationLog::class, [
            'channel' => 'software_webhook',
            'status' => 'sent',
            'template' => 'access_suspended',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function subscriptionWithCustomer(array $overrides = []): Subscription
    {
        $user = User::factory()->create();
        BillingCustomer::query()->create([
            'team_id' => $user->current_team_id,
            'billing_provider' => 'asaas',
            'external_customer_id' => 'cus_automation_1',
            'name' => 'Cliente Automação',
            'email' => 'financeiro@automation.test',
            'tax_id' => '12345678000190',
            'cellphone' => '11999999999',
        ]);
        $product = Product::query()->create([
            'name' => 'Automation CRM '.uniqid(),
            'slug' => 'automation-crm-'.uniqid(),
            'description' => 'CRM',
        ]);

        return Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Profissional',
            'status' => 'pending',
            'access_status' => 'pending',
            'billing_cycle' => 'monthly',
            'amount' => 199,
            'seats' => 1,
            'billing_provider' => 'asaas',
            'external_subscription_id' => 'sub_automation_1',
            ...$overrides,
        ]);
    }
}
