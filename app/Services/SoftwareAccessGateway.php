<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SoftwareAccessGateway
{
    public function sync(Subscription $subscription, string $eventId): void
    {
        $subscription->loadMissing(['product', 'team.billingCustomer']);
        $product = $subscription->product;
        if (! $product->provisioning_webhook_url) {
            return;
        }

        $customerName = $subscription->team->billingCustomer()->value('name') ?? $subscription->team->name;

        $payload = [
            'event' => 'subscription.access_updated',
            'event_id' => $eventId,
            'occurred_at' => now()->toIso8601String(),
            'customer' => [
                'team_id' => $subscription->team_id,
                'external_reference' => 'hub-team-'.$subscription->team_id,
                'name' => $customerName,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'external_id' => $subscription->external_subscription_id,
                'status' => $subscription->status,
                'access_status' => $subscription->access_status,
                'reason' => $subscription->access_reason,
                'seats' => $subscription->seats,
            ],
            'product' => ['id' => $product->id, 'slug' => $product->slug, 'name' => $product->name],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) $product->provisioning_webhook_secret);

        $log = CommunicationLog::query()->firstOrCreate(
            ['deduplication_key' => $eventId.'-software-'.$product->id],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'channel' => 'software_webhook',
                'recipient' => $product->provisioning_webhook_url,
                'template' => 'access_'.$subscription->access_status,
                'status' => 'queued',
                'context' => $payload,
                'scheduled_at' => now(),
            ],
        );

        if (! $log->wasRecentlyCreated) {
            return;
        }

        $response = Http::withHeaders([
            'X-Hub-Event' => 'subscription.access_updated',
            'X-Hub-Signature' => 'sha256='.$signature,
        ])->acceptJson()->withBody($body, 'application/json')->timeout(15)->retry(2, 250)->post($product->provisioning_webhook_url);

        if ($response->failed()) {
            $log->update(['status' => 'failed', 'error_message' => 'O software recusou a atualização de acesso.']);
            throw new RuntimeException('O webhook de acesso do software '.$product->name.' falhou.');
        }

        $log->update(['status' => 'sent', 'sent_at' => now()]);
    }
}
