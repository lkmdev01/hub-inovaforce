<?php

namespace Tests\Feature;

use App\Models\BillingCustomer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\ClientPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_start_a_read_only_customer_preview(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = $this->customerTeam();

        $this->actingAs($admin)
            ->post(route('admin.customers.preview.start', $team))
            ->assertRedirect(route('dashboard', ['current_team' => $team]))
            ->assertSessionHas(ClientPreview::SESSION_TEAM_ID, $team->id);

        $this->get(route('dashboard', ['current_team' => $team]))
            ->assertOk()
            ->assertSee('Visualizando como cliente: '.$team->name)
            ->assertSee('Modo somente leitura')
            ->assertSee('Portal simulado')
            ->assertDontSee('Administração')
            ->assertSee(route('subscriptions.index', ['current_team' => $team]), false);

        $this->get(route('subscriptions.index', ['current_team' => $team]))
            ->assertOk()
            ->assertSee('Assinaturas');
        $this->get(route('invoices.index', ['current_team' => $team]))->assertOk();
        $this->get(route('products.index', ['current_team' => $team]))->assertOk();
        $this->get(route('customer.show', ['current_team' => $team]))
            ->assertOk()
            ->assertSee('Somente leitura');

        $this->assertFalse($admin->fresh()->belongsToTeam($team));
        $this->assertNotSame($team->id, $admin->fresh()->current_team_id);
    }

    public function test_preview_cannot_change_customer_data_or_a_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = $this->customerTeam();
        $product = Product::query()->create([
            'name' => 'Inova Desk',
            'slug' => 'inova-desk',
            'description' => 'Atendimento',
        ]);
        $subscription = Subscription::query()->create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'plan_name' => 'Essencial',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 99,
            'seats' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.customers.preview.start', $team));

        $this->put(route('customer.update', ['current_team' => $team]), [
            'name' => 'Nome alterado',
        ])->assertForbidden();

        $this->post(route('subscriptions.toggle', [
            'current_team' => $team,
            'subscription' => $subscription,
        ]))->assertForbidden();

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('Cliente Exemplo', $team->billingCustomer->fresh()->name);
    }

    public function test_administrator_can_stop_preview_and_return_to_customer_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = $this->customerTeam();

        $this->actingAs($admin)->post(route('admin.customers.preview.start', $team));

        $this->post(route('admin.customers.preview.stop'))
            ->assertRedirect(route('admin.customers.show', $team))
            ->assertSessionMissing(ClientPreview::SESSION_TEAM_ID)
            ->assertSessionMissing(ClientPreview::SESSION_EXPIRES_AT);

        $this->get(route('dashboard', ['current_team' => $team]))->assertForbidden();
    }

    public function test_customer_cannot_start_or_stop_an_administrator_preview(): void
    {
        $customer = User::factory()->create();
        $team = $this->customerTeam();

        $this->actingAs($customer)
            ->post(route('admin.customers.preview.start', $team))
            ->assertForbidden();

        $this->post(route('admin.customers.preview.stop'))->assertForbidden();
    }

    public function test_administrator_normal_navigation_only_shows_administration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Administração')
            ->assertDontSee('Workspace');
    }

    public function test_expired_preview_no_longer_grants_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $team = $this->customerTeam();

        $this->actingAs($admin)
            ->withSession([
                ClientPreview::SESSION_TEAM_ID => $team->id,
                ClientPreview::SESSION_EXPIRES_AT => now()->subMinute()->timestamp,
            ])
            ->get(route('dashboard', ['current_team' => $team]))
            ->assertForbidden();
    }

    private function customerTeam(): Team
    {
        $team = Team::factory()->create(['name' => 'Empresa Cliente']);

        BillingCustomer::query()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Exemplo',
            'email' => 'financeiro@cliente.test',
            'tax_id' => '12345678000190',
            'cellphone' => '11999999999',
        ]);

        return $team;
    }
}
