<?php

namespace Tests\Feature;

use App\Models\BillingCustomer;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.asaas.api_key' => null]);
    }

    public function test_customer_cannot_access_the_administration_area(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_administrator_can_view_the_business_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Visão geral do negócio')
            ->assertSee('Clientes');
    }

    public function test_administrator_can_create_a_customer_company_and_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'company_name' => 'Acme Tecnologia Ltda.',
            'contact_name' => 'Maria da Silva',
            'email' => 'maria@acme.test',
            'password' => 'senha-segura-123',
            'tax_id' => '12.345.678/0001-90',
            'cellphone' => '(11) 99999-9999',
            'zip_code' => '01310-100',
        ]);

        $team = Team::query()->where('name', 'Acme Tecnologia Ltda.')->firstOrFail();
        $user = User::query()->where('email', 'maria@acme.test')->firstOrFail();

        $response->assertRedirect(route('admin.customers.show', $team));
        $this->assertTrue($user->belongsToTeam($team));
        $this->assertDatabaseHas(BillingCustomer::class, [
            'team_id' => $team->id,
            'name' => 'Acme Tecnologia Ltda.',
            'tax_id' => '12.345.678/0001-90',
        ]);
        $this->actingAs($admin)->get(route('admin.customers.show', $team))->assertOk()->assertSee('Maria da Silva');
    }

    public function test_administrator_can_update_a_product_and_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create([
            'name' => 'Flow CRM',
            'slug' => 'flow-crm',
            'description' => 'CRM comercial',
        ]);
        $plan = ProductPlan::query()->create([
            'product_id' => $product->id,
            'name' => 'Profissional',
            'billing_cycle' => 'monthly',
            'price' => 179,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Flow CRM Pro',
            'slug' => 'flow-crm-pro',
            'description' => 'CRM comercial atualizado',
            'status' => 'active',
            'accent' => 'sky',
            'features' => "Funil de vendas\nRelatórios",
        ])->assertSessionHas('success');

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Profissional',
            'status' => 'active',
            'billing_cycle' => 'quarterly',
            'billing_type' => 'PIX',
            'price' => 199.90,
            'pricing_model' => 'per_seat',
            'minimum_seats' => 2,
            'maximum_seats' => 50,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'name' => 'Flow CRM Pro', 'slug' => 'flow-crm-pro', 'accent' => 'sky']);
        $this->assertDatabaseHas(ProductPlan::class, ['id' => $plan->id, 'price' => 199.90, 'billing_cycle' => 'quarterly', 'billing_type' => 'PIX', 'pricing_model' => 'per_seat']);
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->assertSee('Flow CRM Pro');
    }

    public function test_administrator_can_create_a_product_with_its_first_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Inova Forms',
            'description' => 'Formulários inteligentes para equipes.',
            'status' => 'draft',
            'accent' => 'fuchsia',
            'features' => "Formulários ilimitados\nAutomações\nAutomações",
            'plan_name' => 'Essencial',
            'billing_cycle' => 'monthly',
            'billing_type' => 'CREDIT_CARD',
            'price' => 79.90,
            'pricing_model' => 'flat',
            'minimum_seats' => 1,
            'maximum_seats' => 25,
        ])->assertSessionHas('success');

        $product = Product::query()->where('slug', 'inova-forms')->firstOrFail();

        $this->assertSame(['Formulários ilimitados', 'Automações'], $product->features);
        $this->assertDatabaseHas(ProductPlan::class, [
            'product_id' => $product->id,
            'name' => 'Essencial',
            'status' => 'active',
            'maximum_seats' => 25,
        ]);
    }

    public function test_administrator_can_add_and_archive_a_plan_without_deleting_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create(['name' => 'Desk One', 'slug' => 'desk-one', 'description' => 'Suporte']);

        $this->actingAs($admin)->post(route('admin.plans.store', $product), [
            'name' => 'Enterprise',
            'status' => 'active',
            'billing_cycle' => 'yearly',
            'billing_type' => 'CREDIT_CARD',
            'price' => 3990,
            'pricing_model' => 'flat',
            'minimum_seats' => 1,
        ])->assertSessionHas('success');

        $plan = $product->plans()->where('name', 'Enterprise')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Enterprise',
            'status' => 'inactive',
            'billing_cycle' => 'yearly',
            'billing_type' => 'CREDIT_CARD',
            'price' => 3990,
            'pricing_model' => 'flat',
            'minimum_seats' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas(ProductPlan::class, ['id' => $plan->id, 'status' => 'inactive']);
    }

    public function test_administrator_can_cancel_a_local_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $product = Product::query()->create(['name' => 'Desk One', 'slug' => 'desk-one', 'description' => 'Suporte']);
        $subscription = Subscription::query()->create([
            'team_id' => $customer->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Essencial',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 89,
            'seats' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.subscriptions.cancel', $subscription))
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk()->assertSee('Desk One');
    }
}
