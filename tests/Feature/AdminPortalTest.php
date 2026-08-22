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
            'description' => 'CRM comercial atualizado',
            'status' => 'active',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'price' => 199.90,
            'abacatepay_product_id' => 'prod_profissional',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'name' => 'Flow CRM Pro']);
        $this->assertDatabaseHas(ProductPlan::class, ['id' => $plan->id, 'price' => 199.90, 'abacatepay_product_id' => 'prod_profissional']);
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->assertSee('Flow CRM Pro');
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
