<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $subscription_id
 * @property string $provider
 * @property string $external_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $automation_status
 * @property int $automation_attempts
 * @property Carbon|null $automation_attempted_at
 * @property Carbon|null $automation_completed_at
 * @property string|null $automation_error
 */
#[Fillable(['provider', 'external_id', 'event', 'subscription_id', 'payload', 'processed_at', 'automation_status', 'automation_attempts', 'automation_attempted_at', 'automation_completed_at', 'automation_error'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'automation_attempts' => 'integer',
            'automation_attempted_at' => 'datetime',
            'automation_completed_at' => 'datetime',
        ];
    }
}
