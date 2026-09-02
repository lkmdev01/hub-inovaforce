<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_must_accept_terms_before_using_the_portal(): void
    {
        $user = User::factory()->create(['accepted_terms_at' => null]);

        $this->actingAs($user)->get(route('dashboard', ['current_team' => $user->currentTeam]))
            ->assertRedirect(route('legal.accept'));

        $this->post(route('legal.accept.store'), ['terms' => '1'])->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->refresh()->accepted_terms_at);
    }

    public function test_successful_admin_changes_are_audited_without_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.customer-groups.store'), [
            'name' => 'Clientes estratégicos', 'description' => 'Conta prioritária', 'color' => 'violet', 'active' => '1',
        ])->assertSessionHas('success');

        $audit = AuditLog::query()->firstOrFail();
        $this->assertSame('admin.customer-groups.store', $audit->action);
        $this->assertSame(['name', 'description', 'color', 'active'], $audit->metadata['changed_fields']);
        $this->assertArrayNotHasKey('values', $audit->metadata);
    }
}
