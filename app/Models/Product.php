<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property string $status
 * @property string $accent
 * @property array<int, string>|null $features
 * @property bool $fiscal_enabled
 * @property string|null $municipal_service_id
 * @property string|null $municipal_service_code
 * @property string|null $municipal_service_name
 * @property string|null $fiscal_service_description
 * @property string|null $fiscal_observations
 * @property string $fiscal_deductions
 * @property string $fiscal_effective_period
 * @property array<string, mixed>|null $fiscal_taxes
 * @property string|null $provisioning_webhook_url
 * @property string|null $provisioning_webhook_secret
 * @property-read Collection<int, ProductPlan> $plans
 */
#[Fillable(['name', 'slug', 'description', 'status', 'accent', 'features', 'fiscal_enabled', 'municipal_service_id', 'municipal_service_code', 'municipal_service_name', 'fiscal_service_description', 'fiscal_observations', 'fiscal_deductions', 'fiscal_effective_period', 'fiscal_taxes', 'provisioning_webhook_url', 'provisioning_webhook_secret'])]
class Product extends Model
{
    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<ProductPlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(ProductPlan::class)
            ->orderBy('price')
            ->orderBy('name');
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'fiscal_enabled' => 'boolean',
            'fiscal_deductions' => 'decimal:2',
            'fiscal_taxes' => 'array',
            'provisioning_webhook_secret' => 'encrypted',
        ];
    }
}
