<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_their_billing_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('subscriptions.index'))->assertOk();
        $this->actingAs($user)->get(route('invoices.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
    }

    public function test_customer_can_issue_an_invoice_for_their_subscription(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Flow CRM',
            'slug' => 'flow-crm',
            'description' => 'CRM',
        ]);
        $subscription = Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Profissional',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 179,
            'seats' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', ['subscription' => $subscription]));

        $response->assertRedirect();
        $this->assertDatabaseHas(Invoice::class, [
            'team_id' => $user->current_team_id,
            'subscription_id' => $subscription->id,
            'total' => 179,
            'status' => 'open',
        ]);
    }

    public function test_customer_cannot_change_another_teams_subscription(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::query()->create(['name' => 'Flow CRM', 'slug' => 'flow-crm', 'description' => 'CRM']);
        $subscription = Subscription::query()->create([
            'team_id' => $other->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Essencial',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 89,
            'seats' => 1,
        ]);

        $this->actingAs($user)->post(route('subscriptions.toggle', [
            'current_team' => $user->currentTeam,
            'subscription' => $subscription,
        ]))->assertNotFound();
    }
}
