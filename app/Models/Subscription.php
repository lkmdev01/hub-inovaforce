<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $product_id
 * @property int|null $product_plan_id
 * @property int|null $pending_product_plan_id
 * @property string $plan_name
 * @property string $status
 * @property string $access_status
 * @property string|null $access_reason
 * @property string $billing_cycle
 * @property string $amount
 * @property int $seats
 * @property string|null $billing_provider
 * @property string|null $external_checkout_id
 * @property string|null $external_subscription_id
 * @property string|null $external_payment_id
 * @property string|null $checkout_url
 * @property Carbon|null $renews_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $access_updated_at
 * @property Carbon|null $fiscal_configured_at
 * @property-read Team $team
 * @property-read Product $product
 * @property-read ProductPlan|null $plan
 * @property-read ProductPlan|null $pendingPlan
 */
#[Fillable(['team_id', 'product_id', 'product_plan_id', 'pending_product_plan_id', 'plan_name', 'status', 'access_status', 'access_reason', 'access_updated_at', 'fiscal_configured_at', 'billing_cycle', 'amount', 'seats', 'billing_provider', 'external_checkout_id', 'external_subscription_id', 'external_payment_id', 'renews_at', 'canceled_at', 'checkout_url'])]
class Subscription extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if ($subscription->access_status) {
                return;
            }

            $subscription->access_status = match ($subscription->status) {
                'active', 'trialing' => 'active',
                'past_due' => 'suspended',
                'canceled' => 'revoked',
                default => 'pending',
            };
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsTo<ProductPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductPlan::class, 'product_plan_id');
    }

    /** @return BelongsTo<ProductPlan, $this> */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(ProductPlan::class, 'pending_product_plan_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'renews_at' => 'datetime',
            'canceled_at' => 'datetime',
            'access_updated_at' => 'datetime',
            'fiscal_configured_at' => 'datetime',
        ];
    }
}
