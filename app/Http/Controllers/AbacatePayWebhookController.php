<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbacatePayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->validSecret($request) && $this->validSignature($request), 401);

        $payload = $request->json()->all();
        $eventId = data_get($payload, 'id');
        $eventName = data_get($payload, 'event');

        abort_unless(is_string($eventId) && is_string($eventName), 422);

        if (WebhookEvent::query()->where('provider', 'abacatepay')->where('external_id', $eventId)->exists()) {
            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($payload, $eventId, $eventName): void {
            $subscription = $this->findSubscription($payload);

            if ($subscription) {
                $this->applyEvent($subscription, $eventName, $payload);
            }

            WebhookEvent::query()->create([
                'provider' => 'abacatepay',
                'external_id' => $eventId,
                'event' => $eventName,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function findSubscription(array $payload): ?Subscription
    {
        $remoteSubscriptionId = data_get($payload, 'data.subscription.id');
        $checkoutId = data_get($payload, 'data.checkout.id');
        $externalId = data_get($payload, 'data.checkout.externalId');

        if ($remoteSubscriptionId && $subscription = Subscription::query()->where('abacatepay_subscription_id', $remoteSubscriptionId)->first()) {
            return $subscription;
        }

        if ($checkoutId && $subscription = Subscription::query()->where('abacatepay_checkout_id', $checkoutId)->first()) {
            return $subscription;
        }

        if (is_string($externalId) && Str::startsWith($externalId, 'hub-subscription-')) {
            return Subscription::query()->find(Str::after($externalId, 'hub-subscription-'));
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function applyEvent(Subscription $subscription, string $eventName, array $payload): void
    {
        $remoteId = data_get($payload, 'data.subscription.id');

        match ($eventName) {
            'subscription.completed', 'subscription.renewed' => $subscription->update([
                'status' => 'active',
                'abacatepay_subscription_id' => $remoteId ?? $subscription->abacatepay_subscription_id,
                'billing_provider' => 'abacatepay',
                'external_subscription_id' => $remoteId ?? $subscription->external_subscription_id,
                'renews_at' => $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'canceled_at' => null,
            ]),
            'subscription.cancelled' => $subscription->update([
                'status' => 'canceled',
                'canceled_at' => data_get($payload, 'data.subscription.canceledAt', now()),
                'renews_at' => null,
            ]),
            'subscription.payment_failed' => $subscription->update(['status' => 'past_due']),
            'subscription.plan_changed' => $this->applyPendingPlan($subscription),
            default => null,
        };

        if ($eventName === 'subscription.renewed') {
            $amount = (int) data_get($payload, 'data.payment.paidAmount', data_get($payload, 'data.subscription.amount', 0));
            Invoice::query()->firstOrCreate(
                ['number' => 'ABP-'.Str::after((string) data_get($payload, 'data.payment.id', Str::random(10)), '_')],
                [
                    'team_id' => $subscription->team_id,
                    'subscription_id' => $subscription->id,
                    'status' => 'paid',
                    'currency' => 'BRL',
                    'subtotal' => $amount / 100,
                    'total' => $amount / 100,
                    'issued_at' => today(),
                    'due_at' => today(),
                    'paid_at' => now(),
                ]
            );
        }
    }

    private function applyPendingPlan(Subscription $subscription): bool
    {
        if (! $subscription->pendingPlan) {
            return false;
        }

        $plan = $subscription->pendingPlan;

        return $subscription->update([
            'product_plan_id' => $plan->id,
            'pending_product_plan_id' => null,
            'plan_name' => $plan->name,
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->price,
        ]);
    }

    private function validSecret(Request $request): bool
    {
        $expected = (string) config('services.abacatepay.webhook_secret');

        return $expected !== '' && hash_equals($expected, (string) $request->query('webhookSecret'));
    }

    private function validSignature(Request $request): bool
    {
        $signature = (string) $request->header('X-Webhook-Signature');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), (string) config('services.abacatepay.webhook_public_key'), true));

        return $signature !== '' && hash_equals($expected, $signature);
    }
}
