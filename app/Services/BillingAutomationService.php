<?php

namespace App\Services;

use App\Models\AutomationAlert;
use App\Models\CommunicationLog;
use App\Models\FinancialEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Notifications\BillingEventNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class BillingAutomationService
{
    public function __construct(
        private readonly AsaasFiscalService $fiscal,
        private readonly WhatsAppGateway $whatsApp,
        private readonly SoftwareAccessGateway $softwareAccess,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Subscription $subscription, string $eventId, string $eventName, array $payload): void
    {
        $subscription->loadMissing(['team.billingCustomer', 'product']);
        $paymentId = $this->id(data_get($payload, 'payment.id'));
        $invoice = $paymentId
            ? Invoice::query()->where('billing_provider', 'asaas')->where('external_payment_id', $paymentId)->first()
            : null;

        $this->recordFinancialEvent($subscription, $invoice, $eventId, $eventName, $payload);

        if (in_array($eventName, ['CHECKOUT_PAID', 'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED', 'PAYMENT_OVERDUE', 'PAYMENT_REPROVED_BY_RISK_ANALYSIS', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'SUBSCRIPTION_INACTIVATED', 'SUBSCRIPTION_DELETED', 'CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED'], true)) {
            try {
                $this->softwareAccess->sync($subscription, $eventId);
            } catch (RuntimeException $exception) {
                $this->alert($subscription, 'provisioning_failure', 'Falha ao atualizar acesso no software', $exception->getMessage(), 'provisioning-failure-'.$eventId, 'error');
            }
        }

        if (str_starts_with($eventName, 'INVOICE_')) {
            $document = $this->fiscal->syncDocument($eventName, $payload, $subscription);
            if ($eventName === 'INVOICE_AUTHORIZED' && $document) {
                $this->communicate($subscription, $invoice, $eventId, 'fiscal_authorized', 'Nota fiscal disponível', 'A nota fiscal da sua assinatura foi emitida e já pode ser consultada no Hub.');
            } elseif (in_array($eventName, ['INVOICE_ERROR', 'INVOICE_CANCELLATION_DENIED'], true)) {
                $this->communicate($subscription, $invoice, $eventId, 'fiscal_error', 'Falha na nota fiscal', 'Não foi possível concluir a emissão da nota fiscal. Nossa equipe já foi avisada.');
            }

            return;
        }

        if ($subscription->external_subscription_id && in_array($eventName, ['PAYMENT_CREATED', 'PAYMENT_UPDATED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
            try {
                $this->fiscal->configure($subscription);
            } catch (RuntimeException $exception) {
                $this->alert($subscription, 'fiscal_error', 'Falha ao configurar NFS-e automática', $exception->getMessage(), 'fiscal-api-'.$subscription->id);
            }
        }

        match ($eventName) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->paymentReceived($subscription, $invoice, $eventId),
            'PAYMENT_OVERDUE' => $this->paymentOverdue($subscription, $invoice, $eventId),
            'PAYMENT_REFUNDED', 'PAYMENT_REFUND_IN_PROGRESS' => $this->paymentRefunded($subscription, $invoice, $eventId),
            'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => $this->chargeback($subscription, $invoice, $eventId),
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => $this->paymentFailed($subscription, $invoice, $eventId, 'Pagamento reprovado pela análise de risco.'),
            default => null,
        };
    }

    public function runDunning(): void
    {
        Invoice::query()
            ->with(['subscription.team.billingCustomer'])
            ->whereIn('status', ['open', 'overdue'])
            ->whereDate('due_at', '<=', today())
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $subscription = $invoice->subscription;
                    if (! $subscription) {
                        continue;
                    }

                    $days = max(0, (int) $invoice->due_at->diffInDays(today()));
                    if (! in_array($days, [0, 3, 7, 15], true)) {
                        continue;
                    }

                    $this->communicate(
                        $subscription,
                        $invoice,
                        'dunning-'.$invoice->id.'-'.$days,
                        'overdue_d'.$days,
                        $days === 0 ? 'Sua fatura vence hoje' : 'Fatura pendente há '.$days.' dias',
                        'Existe uma cobrança pendente de R$ '.number_format((float) $invoice->total, 2, ',', '.').'. Acesse o Hub para consultar e pagar.',
                    );
                }
            });
    }

    private function paymentReceived(Subscription $subscription, ?Invoice $invoice, string $eventId): void
    {
        $this->resolveAlerts($subscription, ['payment_failure', 'overdue', 'chargeback']);
        $this->communicate($subscription, $invoice, $eventId, 'payment_received', 'Pagamento confirmado', 'Recebemos o pagamento da sua assinatura. O acesso está liberado e o comprovante pode ser consultado no Hub.');
    }

    private function paymentOverdue(Subscription $subscription, ?Invoice $invoice, string $eventId): void
    {
        $this->alert($subscription, 'overdue', 'Cliente inadimplente', 'A cobrança venceu e o acesso foi suspenso automaticamente.', 'overdue-'.$subscription->id);
        $this->communicate($subscription, $invoice, $eventId, 'payment_overdue', 'Pagamento em atraso', 'Sua cobrança está vencida e o acesso foi temporariamente suspenso. Regularize o pagamento pelo Hub.');
    }

    private function paymentRefunded(Subscription $subscription, ?Invoice $invoice, string $eventId): void
    {
        $this->alert($subscription, 'refund', 'Pagamento estornado', 'Um pagamento foi estornado ou está em processo de estorno.', 'refund-'.$eventId);
        $this->communicate($subscription, $invoice, $eventId, 'payment_refunded', 'Estorno de pagamento', 'O Asaas informou um estorno relacionado à sua assinatura. Consulte os detalhes no Hub.');
    }

    private function chargeback(Subscription $subscription, ?Invoice $invoice, string $eventId): void
    {
        $this->alert($subscription, 'chargeback', 'Chargeback recebido', 'O acesso foi suspenso e o caso exige análise administrativa.', 'chargeback-'.$eventId, 'critical');
        $this->communicate($subscription, $invoice, $eventId, 'chargeback', 'Contestação de pagamento', 'Foi registrada uma contestação relacionada ao pagamento da assinatura. Nossa equipe analisará o caso.');
    }

    private function paymentFailed(Subscription $subscription, ?Invoice $invoice, string $eventId, string $reason): void
    {
        $this->alert($subscription, 'payment_failure', 'Falha no pagamento', $reason, 'payment-failure-'.$eventId);
        $this->communicate($subscription, $invoice, $eventId, 'payment_failed', 'Não foi possível confirmar o pagamento', 'Atualize a forma de pagamento ou tente novamente pelo Hub.');
    }

    /** @param array<string, mixed> $payload */
    private function recordFinancialEvent(Subscription $subscription, ?Invoice $invoice, string $eventId, string $eventName, array $payload): void
    {
        $payment = data_get($payload, 'payment', []);
        $status = is_array($payment) ? ($payment['status'] ?? null) : null;
        $amount = is_array($payment) ? ($payment['value'] ?? null) : null;

        FinancialEvent::query()->firstOrCreate(
            ['provider' => 'asaas', 'external_event_id' => $eventId],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice?->id,
                'type' => $eventName,
                'status' => is_string($status) ? $status : null,
                'amount' => is_numeric($amount) ? $amount : null,
                'title' => $this->eventTitle($eventName),
                'description' => $this->eventDescription($eventName),
                'metadata' => ['payment_id' => $invoice?->external_payment_id],
                'occurred_at' => now(),
            ],
        );
    }

    private function communicate(Subscription $subscription, ?Invoice $invoice, string $key, string $template, string $title, string $message): void
    {
        $customer = $subscription->team->billingCustomer;
        if (! $customer) {
            return;
        }

        $url = route('invoices.index', ['current_team' => $subscription->team]);
        $email = CommunicationLog::query()->firstOrCreate(
            ['deduplication_key' => $key.'-email'],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice?->id,
                'channel' => 'email',
                'recipient' => $customer->email,
                'template' => $template,
                'status' => 'queued',
                'context' => compact('title', 'message', 'url'),
                'scheduled_at' => now(),
            ],
        );

        if ($email->wasRecentlyCreated) {
            try {
                Notification::route('mail', $customer->email)->notify(new BillingEventNotification($title, $message, $url, 'Consultar no Hub'));
                $email->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $exception) {
                $email->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            }
        }

        if ($customer->cellphone) {
            $whatsApp = CommunicationLog::query()->firstOrCreate(
                ['deduplication_key' => $key.'-whatsapp'],
                [
                    'team_id' => $subscription->team_id,
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice?->id,
                    'channel' => 'whatsapp',
                    'recipient' => $customer->cellphone,
                    'template' => $template,
                    'status' => $this->whatsApp->configured() ? 'queued' : 'waiting_configuration',
                    'context' => compact('title', 'message', 'url'),
                    'scheduled_at' => now(),
                ],
            );

            if ($whatsApp->wasRecentlyCreated && $this->whatsApp->configured()) {
                try {
                    $this->whatsApp->send($customer->cellphone, $template, compact('title', 'message', 'url'));
                    $whatsApp->update(['status' => 'sent', 'sent_at' => now()]);
                } catch (Throwable $exception) {
                    $whatsApp->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
                    $this->alert($subscription, 'communication_failure', 'Falha no envio por WhatsApp', $exception->getMessage(), 'whatsapp-failure-'.$key);
                }
            }
        }
    }

    private function alert(Subscription $subscription, string $category, string $title, string $message, string $key, string $severity = 'warning'): void
    {
        AutomationAlert::query()->updateOrCreate(
            ['deduplication_key' => $key],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'category' => $category,
                'severity' => $severity,
                'status' => 'open',
                'title' => $title,
                'message' => $message,
                'action_url' => route('admin.customers.show', $subscription->team),
                'resolved_at' => null,
            ],
        );
    }

    /** @param array<int, string> $categories */
    private function resolveAlerts(Subscription $subscription, array $categories): void
    {
        AutomationAlert::query()->where('subscription_id', $subscription->id)->whereIn('category', $categories)->where('status', 'open')->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    private function eventTitle(string $event): string
    {
        return match ($event) {
            'PAYMENT_CREATED' => 'Cobrança criada',
            'PAYMENT_CONFIRMED' => 'Pagamento confirmado',
            'PAYMENT_RECEIVED' => 'Pagamento recebido',
            'PAYMENT_OVERDUE' => 'Cobrança vencida',
            'PAYMENT_REFUNDED' => 'Pagamento estornado',
            'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE' => 'Chargeback registrado',
            'INVOICE_AUTHORIZED' => 'Nota fiscal emitida',
            'INVOICE_ERROR' => 'Falha na nota fiscal',
            default => str($event)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    private function eventDescription(string $event): string
    {
        return match ($event) {
            'PAYMENT_OVERDUE' => 'O vencimento passou sem confirmação do pagamento.',
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => 'A cobrança foi conciliada automaticamente pelo Asaas.',
            'PAYMENT_REFUNDED', 'PAYMENT_REFUND_IN_PROGRESS' => 'O valor foi ou está sendo devolvido ao pagador.',
            'INVOICE_AUTHORIZED' => 'A prefeitura autorizou a NFS-e.',
            'INVOICE_ERROR' => 'O Asaas informou uma falha no processamento fiscal.',
            default => 'Evento sincronizado automaticamente pelo webhook.',
        };
    }

    private function id(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
