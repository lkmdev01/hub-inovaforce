<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'subscription_id', 'invoice_id', 'billing_provider', 'external_invoice_id', 'external_payment_id', 'status', 'number', 'validation_code', 'pdf_url', 'xml_url', 'amount', 'effective_date', 'error_message', 'payload'])]
class FiscalDocument extends Model
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_date' => 'date', 'payload' => 'array'];
    }
}
