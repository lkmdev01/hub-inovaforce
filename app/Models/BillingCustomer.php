<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $customer_group_id
 * @property string|null $billing_provider
 * @property string|null $external_customer_id
 * @property string $name
 * @property string $email
 * @property string|null $tax_id
 * @property string|null $cellphone
 * @property string|null $zip_code
 * @property Carbon|null $synced_at
 * @property-read Team $team
 * @property-read CustomerGroup|null $group
 */
#[Fillable(['team_id', 'customer_group_id', 'billing_provider', 'external_customer_id', 'name', 'email', 'tax_id', 'cellphone', 'zip_code', 'synced_at'])]
class BillingCustomer extends Model
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }
}
