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
 * @property int|null $subscription_id
 * @property string $number
 * @property string $kind
 * @property string|null $description
 * @property string $status
 * @property string|null $billing_provider
 * @property string $total
 * @property string $subtotal
 * @property string|null $external_payment_id
 * @property string|null $payment_url
 * @property string|null $receipt_url
 * @property string|null $bank_slip_url
 * @property string|null $failure_reason
 * @property Carbon $issued_at
 * @property Carbon $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property-read Team $team
 * @property-read Subscription|null $subscription
 */
#[Fillable(['team_id', 'subscription_id', 'kind', 'billing_provider', 'external_payment_id', 'payment_url', 'receipt_url', 'bank_slip_url', 'failure_reason', 'number', 'description', 'status', 'currency', 'subtotal', 'total', 'issued_at', 'due_at', 'paid_at', 'refunded_at'])]
class Invoice extends Model
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return HasMany<FiscalDocument, $this> */
    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    /** @return HasMany<FinancialEvent, $this> */
    public function financialEvents(): HasMany
    {
        return $this->hasMany(FinancialEvent::class);
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
