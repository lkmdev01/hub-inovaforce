<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteMasterCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_account_can_be_promoted_to_master_by_email(): void
    {
        $user = User::factory()->unverified()->create(['is_admin' => false]);

        $this->artisan('hub:promote-master', ['email' => strtoupper($user->email)])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_exclusive_promotion_revokes_other_master_accounts(): void
    {
        $oldMaster = User::factory()->create(['is_admin' => true]);
        $newMaster = User::factory()->create(['is_admin' => false]);

        $this->artisan('hub:promote-master', [
            'email' => $newMaster->email,
            '--exclusive' => true,
        ])->assertSuccessful();

        $this->assertFalse($oldMaster->fresh()->is_admin);
        $this->assertTrue($newMaster->fresh()->is_admin);
    }

    public function test_unknown_account_is_not_created_implicitly(): void
    {
        $this->artisan('hub:promote-master', ['email' => 'inexistente@example.com'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'inexistente@example.com']);
    }
}
