<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'color', 'description', 'active'])]
class CustomerGroup extends Model
{
    /** @return HasMany<BillingCustomer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
