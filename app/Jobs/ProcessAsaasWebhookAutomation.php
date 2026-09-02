<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\BillingAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAsaasWebhookAutomation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 180];

    public function __construct(public int $webhookEventId) {}

    public function uniqueId(): string
    {
        return (string) $this->webhookEventId;
    }

    public function handle(BillingAutomationService $automations): void
    {
        $webhook = WebhookEvent::query()->find($this->webhookEventId);
        if (! $webhook || $webhook->automation_status === 'completed') {
            return;
        }

        $webhook->update([
            'automation_status' => 'processing',
            'automation_attempts' => $webhook->automation_attempts + 1,
            'automation_attempted_at' => now(),
            'automation_error' => null,
        ]);

        $subscription = $webhook->subscription_id
            ? Subscription::query()->find($webhook->subscription_id)
            : null;

        if ($subscription) {
            $automations->handle($subscription, $webhook->external_id, $webhook->event, $webhook->payload);
        }

        $webhook->update([
            'automation_status' => 'completed',
            'automation_completed_at' => now(),
            'automation_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->webhookEventId)->update([
            'automation_status' => 'failed',
            'automation_error' => $exception?->getMessage() ?? 'Falha desconhecida na automação.',
        ]);
    }
}
