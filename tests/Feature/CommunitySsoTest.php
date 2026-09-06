<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunitySsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_customer_can_enter_the_community_without_a_second_login(): void
    {
        [$user, $product, $payload] = $this->activeCustomer();

        $response = $this->postJson(
            route('community.sso.launch', ['product' => $product->slug]),
            $payload,
            ['X-Inovaforce-Signature' => 'sha256='.$this->signature($payload, 'test-community-secret')],
        );

        $response->assertOk()->assertJsonStructure(['launch_url', 'expires_at']);

        $launchUrl = $response->json('launch_url');
        $this->get($launchUrl)
            ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam]).'#comunidade');
        $this->assertAuthenticatedAs($user);

        $this->get($launchUrl)->assertGone();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        [, $product, $payload] = $this->activeCustomer();

        $this->postJson(
            route('community.sso.launch', ['product' => $product->slug]),
            $payload,
            ['X-Inovaforce-Signature' => 'sha256='.str_repeat('0', 64)],
        )->assertUnauthorized();
    }

    public function test_customer_without_an_active_subscription_is_rejected(): void
    {
        [$user, $product, $payload] = $this->activeCustomer();
        $user->currentTeam->subscriptions()->update(['status' => 'past_due', 'access_status' => 'suspended']);

        $this->postJson(
            route('community.sso.launch', ['product' => $product->slug]),
            $payload,
            ['X-Inovaforce-Signature' => 'sha256='.$this->signature($payload, 'test-community-secret')],
        )->assertForbidden();
    }

    /** @return array{User, Product, array{email: string, team: string, timestamp: int, nonce: string}} */
    private function activeCustomer(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Mava',
            'slug' => 'mava',
            'description' => 'Produto de teste',
            'status' => 'active',
            'community_sso_enabled' => true,
            'community_sso_secret' => 'test-community-secret',
        ]);
        Subscription::query()->create([
            'team_id' => $user->current_team_id,
            'product_id' => $product->id,
            'plan_name' => 'Mensal',
            'status' => 'active',
            'access_status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 35,
            'seats' => 1,
        ]);
        $payload = [
            'email' => Str::lower($user->email),
            'team' => $user->currentTeam->slug,
            'timestamp' => now()->timestamp,
            'nonce' => Str::random(40),
        ];

        return [$user, $product, $payload];
    }

    /** @param array{email: string, team: string, timestamp: int, nonce: string} $payload */
    private function signature(array $payload, string $secret): string
    {
        return hash_hmac('sha256', implode("\n", [
            $payload['email'],
            $payload['team'],
            (string) $payload['timestamp'],
            $payload['nonce'],
        ]), $secret);
    }
}
