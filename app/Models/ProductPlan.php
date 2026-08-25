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
 * @property string $status
 * @property string $billing_cycle
 * @property string $billing_type
 * @property string $price
 * @property string $pricing_model
 * @property int $minimum_seats
 * @property int|null $maximum_seats
 * @property string|null $billing_provider
 * @property string|null $external_product_id
 * @property-read Product $product
 */
#[Fillable(['product_id', 'name', 'status', 'billing_cycle', 'billing_type', 'price', 'pricing_model', 'minimum_seats', 'maximum_seats', 'billing_provider', 'external_product_id'])]
class ProductPlan extends Model
{
    /** @var array<string, string> */
    public const CYCLES = [
        'weekly' => 'Semanal',
        'biweekly' => 'Quinzenal',
        'monthly' => 'Mensal',
        'bimonthly' => 'Bimestral',
        'quarterly' => 'Trimestral',
        'semiannually' => 'Semestral',
        'yearly' => 'Anual',
    ];

    /** @var array<string, string> */
    public const BILLING_TYPES = [
        'CREDIT_CARD' => 'Cartão de crédito',
        'PIX' => 'Pix',
    ];

    /** @var array<string, string> */
    public const PRICING_MODELS = [
        'flat' => 'Preço fixo',
        'per_seat' => 'Por licença',
    ];

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
        return [
            'price' => 'decimal:2',
            'minimum_seats' => 'integer',
            'maximum_seats' => 'integer',
        ];
    }

    public function totalForSeats(int $seats): float
    {
        return (float) $this->price * ($this->pricing_model === 'per_seat' ? $seats : 1);
    }
}
