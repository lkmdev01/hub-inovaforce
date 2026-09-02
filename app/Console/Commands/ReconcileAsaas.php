<?php

namespace App\Console\Commands;

use App\Http\Controllers\AsaasWebhookController;
use App\Models\Subscription;
use App\Models\SystemRun;
use App\Services\AsaasClient;
use Illuminate\Console\Command;
use Throwable;

class ReconcileAsaas extends Command
{
    protected $signature = 'asaas:reconcile';

    protected $description = 'Recupera cobranças do Asaas que possam não ter chegado por webhook';

    public function handle(AsaasClient $asaas, AsaasWebhookController $webhooks): int
    {
        $processed = 0;

        try {
            Subscription::query()
                ->where('billing_provider', 'asaas')
                ->whereNotNull('external_subscription_id')
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->chunkById(50, function ($subscriptions) use ($asaas, $webhooks, &$processed): void {
                    foreach ($subscriptions as $subscription) {
                        $result = $asaas->subscriptionPayments((string) $subscription->external_subscription_id);
                        $payments = data_get($result, 'data', []);

                        if (! is_array($payments)) {
                            continue;
                        }

                        foreach ($payments as $payment) {
                            if (! is_array($payment) || ! isset($payment['id'], $payment['status'])) {
                                continue;
                            }

                            $event = $this->eventForStatus((string) $payment['status']);
                            $eventId = 'reconcile-'.(string) $payment['id'].'-'.strtolower((string) $payment['status']);
                            $webhooks->processPayload(['id' => $eventId, 'event' => $event, 'payment' => $payment], $eventId, $event, dispatchAutomations: false);
                            $processed++;
                        }
                    }
                });

            SystemRun::query()->updateOrCreate(['name' => 'asaas-reconciliation'], [
                'status' => 'ok', 'ran_at' => now(), 'details' => ['payments_seen' => $processed], 'error_message' => null,
            ]);

            $this->info("Reconciliação concluída: {$processed} cobrança(s) verificadas.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            SystemRun::query()->updateOrCreate(['name' => 'asaas-reconciliation'], [
                'status' => 'failed', 'ran_at' => now(), 'error_message' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function eventForStatus(string $status): string
    {
        return match ($status) {
            'RECEIVED', 'RECEIVED_IN_CASH' => 'PAYMENT_RECEIVED',
            'CONFIRMED' => 'PAYMENT_CONFIRMED',
            'OVERDUE' => 'PAYMENT_OVERDUE',
            'REFUNDED' => 'PAYMENT_REFUNDED',
            'REFUND_REQUESTED' => 'PAYMENT_REFUND_IN_PROGRESS',
            'CHARGEBACK_REQUESTED' => 'PAYMENT_CHARGEBACK_REQUESTED',
            'CHARGEBACK_DISPUTE' => 'PAYMENT_CHARGEBACK_DISPUTE',
            'DELETED' => 'PAYMENT_DELETED',
            default => 'PAYMENT_UPDATED',
        };
    }
}
