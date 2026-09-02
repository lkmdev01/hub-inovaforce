<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'subscription_id', 'invoice_id', 'channel', 'recipient', 'template', 'status', 'attempts', 'deduplication_key', 'context', 'scheduled_at', 'last_attempted_at', 'sent_at', 'error_message'])]
class CommunicationLog extends Model
{
    /** @return array<string, mixed> */
    public function contextData(): array
    {
        $context = $this->getRawOriginal('context');
        if (is_string($context)) {
            $decoded = json_decode($context, true);

            return is_array($decoded) ? $decoded : [];
        }

        $cast = $this->getAttribute('context');

        return is_array($cast) ? $cast : [];
    }

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
        return ['context' => 'array', 'attempts' => 'integer', 'scheduled_at' => 'datetime', 'last_attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
