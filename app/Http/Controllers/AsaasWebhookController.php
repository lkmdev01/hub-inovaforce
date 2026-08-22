<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\BillingAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, BillingAutomationService $automations): JsonResponse
    {
        abort_unless($this->validToken($request), 401);

        $payload = $request->json()->all();
        $eventId = data_get($payload, 'id');
        $eventName = data_get($payload, 'event');
        abort_unless(is_string($eventId) && is_string($eventName), 422);

        $result = DB::transaction(function () use ($payload, $eventId, $eventName): array {
            $webhook = WebhookEvent::query()->firstOrCreate(
                ['provider' => 'asaas', 'external_id' => $eventId],
                ['event' => $eventName, 'payload' => $payload, 'processed_at' => now()],
            );

            if (! $webhook->wasRecentlyCreated) {
                return ['processed' => false, 'subscription_id' => null];
            }

            $subscription = $this->findSubscription($payload);
            if ($subscription) {
                $this->applyEvent($subscription, $eventName, $payload);
            }

            return ['processed' => true, 'subscription_id' => $subscription?->id];
        });

        if ($result['processed'] && $result['subscription_id']) {
            $subscription = Subscription::query()->find($result['subscription_id']);
            if ($subscription) {
                $automations->handle($subscription, $eventId, $eventName, $payload);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function findSubscription(array $payload): ?Subscription
    {
        $checkoutId = $this->firstId($payload, ['checkout.id', 'payment.checkoutSession.id', 'payment.checkoutSession']);
        if ($checkoutId && $subscription = Subscription::query()->where('billing_provider', 'asaas')->where('external_checkout_id', $checkoutId)->first()) {
            return $subscription;
        }

        $remoteSubscriptionId = $this->firstId($payload, ['subscription.id', 'payment.subscription.id', 'payment.subscription']);
        if ($remoteSubscriptionId && $subscription = Subscription::query()->where('billing_provider', 'asaas')->where('external_subscription_id', $remoteSubscriptionId)->first()) {
            return $subscription;
        }

        $externalReference = $this->firstId($payload, [
            'checkout.externalReference',
            'payment.externalReference',
            'subscription.externalReference',
        ]);

        if ($externalReference && preg_match('/hub-subscription-(\d+)/', $externalReference, $matches)) {
            return Subscription::query()->find((int) $matches[1]);
        }

        $fiscalPaymentId = $this->firstId($payload, ['invoice.payment.id', 'invoice.payment']);
        if ($fiscalPaymentId) {
            return Invoice::query()
                ->where('billing_provider', 'asaas')
                ->where('external_payment_id', $fiscalPaymentId)
                ->first()
                ?->subscription;
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function applyEvent(Subscription $subscription, string $eventName, array $payload): void
    {
        $remoteSubscriptionId = $this->firstId($payload, ['subscription.id', 'payment.subscription.id', 'payment.subscription']);
        $paymentId = $this->firstId($payload, ['payment.id']);

        $identifiers = array_filter([
            'billing_provider' => 'asaas',
            'external_subscription_id' => $remoteSubscriptionId,
            'external_payment_id' => $paymentId,
        ]);
        $subscription->update($identifiers);

        match ($eventName) {
            'CHECKOUT_PAID', 'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->activate($subscription),
            'PAYMENT_OVERDUE', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => $subscription->update([
                'status' => 'past_due',
                'access_status' => 'suspended',
                'access_reason' => $eventName,
                'access_updated_at' => now(),
            ]),
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => $subscription->update([
                'access_status' => 'suspended',
                'access_reason' => $eventName,
                'access_updated_at' => now(),
            ]),
            'CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED' => $this->cancelPending($subscription),
            'SUBSCRIPTION_INACTIVATED', 'SUBSCRIPTION_DELETED' => $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'renews_at' => null,
                'access_status' => 'revoked',
                'access_reason' => $eventName,
                'access_updated_at' => now(),
            ]),
            default => null,
        };

        if (Str::startsWith($eventName, 'PAYMENT_') && $paymentId) {
            $this->syncInvoice($subscription, $eventName, $payload, $paymentId);
        }
    }

    private function activate(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'active',
            'renews_at' => $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            'canceled_at' => null,
            'access_status' => 'active',
            'access_reason' => null,
            'access_updated_at' => now(),
        ]);
    }

    private function cancelPending(Subscription $subscription): void
    {
        if ($subscription->status !== 'pending') {
            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'renews_at' => null,
            'access_status' => 'revoked',
            'access_reason' => 'checkout_canceled',
            'access_updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function syncInvoice(Subscription $subscription, string $eventName, array $payload, string $paymentId): void
    {
        $payment = data_get($payload, 'payment', []);
        if (! is_array($payment)) {
            return;
        }

        $status = match ($eventName) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => 'paid',
            'PAYMENT_OVERDUE' => 'overdue',
            'PAYMENT_REFUNDED' => 'refunded',
            'PAYMENT_REFUND_IN_PROGRESS' => 'refund_pending',
            'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => 'chargeback',
            'PAYMENT_DELETED' => 'canceled',
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => 'failed',
            default => $this->mapPaymentStatus((string) ($payment['status'] ?? 'PENDING')),
        };
        $amount = (float) ($payment['value'] ?? $subscription->amount);
        $dueAt = $this->date($payment['dueDate'] ?? null) ?? today();
        $paidAt = $status === 'paid'
            ? ($this->date($payment['paymentDate'] ?? $payment['clientPaymentDate'] ?? null) ?? now())
            : null;

        Invoice::query()->updateOrCreate(
            ['billing_provider' => 'asaas', 'external_payment_id' => $paymentId],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'number' => 'ASAAS-'.Str::upper(Str::after($paymentId, 'pay_')),
                'status' => $status,
                'currency' => 'BRL',
                'subtotal' => $amount,
                'total' => $amount,
                'issued_at' => $this->date($payment['dateCreated'] ?? null) ?? today(),
                'due_at' => $dueAt,
                'paid_at' => $paidAt,
                'payment_url' => $payment['invoiceUrl'] ?? null,
                'receipt_url' => $payment['transactionReceiptUrl'] ?? $payment['receiptUrl'] ?? null,
                'bank_slip_url' => $payment['bankSlipUrl'] ?? null,
                'failure_reason' => $payment['failureReason'] ?? null,
                'refunded_at' => in_array($status, ['refunded', 'refund_pending'], true) ? now() : null,
            ],
        );
    }

    private function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE' => 'overdue',
            'REFUNDED' => 'refunded',
            'REFUND_REQUESTED' => 'refund_pending',
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => 'chargeback',
            'DELETED' => 'canceled',
            default => 'open',
        };
    }

    private function validToken(Request $request): bool
    {
        $expected = (string) config('services.asaas.webhook_token');

        return $expected !== '' && hash_equals($expected, (string) $request->header('asaas-access-token'));
    }

    /** @param array<string, mixed> $payload
     *  @param array<int, string> $paths
     */
    private function firstId(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
