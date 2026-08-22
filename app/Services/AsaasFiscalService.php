<?php

namespace App\Services;

use App\Models\AutomationAlert;
use App\Models\FiscalDocument;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Throwable;

class AsaasFiscalService
{
    public function __construct(private readonly AsaasClient $asaas) {}

    public function configure(Subscription $subscription): bool
    {
        $subscription->loadMissing('product');
        $product = $subscription->product;

        if ($subscription->billing_provider !== 'asaas' || ! $subscription->external_subscription_id || ! $product->fiscal_enabled) {
            return false;
        }

        if ($subscription->fiscal_configured_at) {
            return true;
        }

        if (! $this->productIsReady($subscription)) {
            $this->alert($subscription, 'fiscal_configuration', 'Configuração fiscal incompleta', 'Complete o serviço municipal, a descrição do serviço e os impostos do produto antes de emitir NFS-e.');

            return false;
        }

        $this->asaas->configureSubscriptionInvoices($subscription->external_subscription_id, $product);
        $subscription->update(['fiscal_configured_at' => now()]);
        $this->resolveAlert('fiscal-configuration-'.$subscription->id);

        return true;
    }

    /** @param array<string, mixed> $payload */
    public function syncDocument(string $eventName, array $payload, ?Subscription $subscription): ?FiscalDocument
    {
        $remote = data_get($payload, 'invoice');
        if (! is_array($remote) || ! is_string($remote['id'] ?? null)) {
            return null;
        }

        $paymentId = $this->id($remote['payment'] ?? null);
        $localInvoice = $paymentId ? Invoice::query()->where('billing_provider', 'asaas')->where('external_payment_id', $paymentId)->first() : null;
        $subscription ??= $localInvoice?->subscription;
        if (! $subscription) {
            return null;
        }

        $status = match ($eventName) {
            'INVOICE_AUTHORIZED' => 'authorized',
            'INVOICE_CANCELED' => 'canceled',
            'INVOICE_ERROR', 'INVOICE_CANCELLATION_DENIED' => 'error',
            'INVOICE_SYNCHRONIZED' => 'synchronized',
            'INVOICE_PROCESSING_CANCELLATION' => 'processing_cancellation',
            default => 'scheduled',
        };

        $document = FiscalDocument::query()->updateOrCreate(
            ['billing_provider' => 'asaas', 'external_invoice_id' => $remote['id']],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $localInvoice?->id,
                'external_payment_id' => $paymentId,
                'status' => $status,
                'number' => $remote['number'] ?? null,
                'validation_code' => $remote['validationCode'] ?? null,
                'pdf_url' => $remote['pdfUrl'] ?? null,
                'xml_url' => $remote['xmlUrl'] ?? null,
                'amount' => $remote['value'] ?? null,
                'effective_date' => $this->date($remote['effectiveDate'] ?? null),
                'error_message' => data_get($remote, 'errors.0.description') ?? $remote['errorMessage'] ?? null,
                'payload' => $remote,
            ],
        );

        if ($status === 'error') {
            $this->alert($subscription, 'fiscal_error', 'Falha na emissão da nota fiscal', $document->error_message ?: 'O Asaas informou uma falha fiscal. Consulte a configuração e os logs.');
        } elseif ($status === 'authorized') {
            $this->resolveAlert('fiscal-error-'.$subscription->id);
        }

        return $document;
    }

    private function productIsReady(Subscription $subscription): bool
    {
        $product = $subscription->product;

        return filled($product->municipal_service_id ?: $product->municipal_service_code)
            && filled($product->municipal_service_name)
            && filled($product->fiscal_service_description)
            && is_array($product->fiscal_taxes);
    }

    private function alert(Subscription $subscription, string $category, string $title, string $message): void
    {
        AutomationAlert::query()->updateOrCreate(
            ['deduplication_key' => str_replace('_', '-', $category).'-'.$subscription->id],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'category' => $category,
                'severity' => 'error',
                'status' => 'open',
                'title' => $title,
                'message' => $message,
                'action_url' => route('admin.products.index'),
                'resolved_at' => null,
            ],
        );
    }

    private function resolveAlert(string $key): void
    {
        AutomationAlert::query()->where('deduplication_key', $key)->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    private function id(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_array($value) && is_string($value['id'] ?? null) ? $value['id'] : null;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
