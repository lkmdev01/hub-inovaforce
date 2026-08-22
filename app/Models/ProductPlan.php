<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $billing_cycle
 * @property string $price
 * @property string|null $billing_provider
 * @property string|null $external_product_id
 * @property string|null $abacatepay_product_id
 * @property-read Product $product
 */
#[Fillable(['product_id', 'name', 'billing_cycle', 'price', 'billing_provider', 'external_product_id', 'abacatepay_product_id'])]
class ProductPlan extends Model
{
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
