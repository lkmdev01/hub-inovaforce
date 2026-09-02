<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialEvent;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\AsaasClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AdminPaymentController extends Controller
{
    public function store(Request $request, Team $team, AsaasClient $asaas): RedirectResponse
    {
        $customer = $team->billingCustomer;
        abort_unless($customer?->external_customer_id && $customer->billing_provider === 'asaas', 422, 'Sincronize o cliente com o Asaas antes de criar uma cobrança.');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'billing_type' => ['required', Rule::in(['UNDEFINED', 'PIX', 'BOLETO'])],
            'value' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $remote = $asaas->createPayment($customer, [
                'billingType' => $data['billing_type'],
                'value' => (float) $data['value'],
                'dueDate' => $data['due_date'],
                'description' => $data['description'],
                'externalReference' => 'hub-one-off-'.$team->id.'-'.Str::uuid(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $paymentId = (string) ($remote['id'] ?? '');
        abort_if($paymentId === '', 502, 'O Asaas não retornou o identificador da cobrança.');

        $invoice = Invoice::query()->create([
            'team_id' => $team->id,
            'kind' => 'one_off',
            'billing_provider' => 'asaas',
            'external_payment_id' => $paymentId,
            'number' => 'ASAAS-'.Str::upper(Str::after($paymentId, 'pay_')),
            'description' => $data['description'],
            'status' => 'open',
            'currency' => 'BRL',
            'subtotal' => $data['value'],
            'total' => $data['value'],
            'issued_at' => today(),
            'due_at' => $data['due_date'],
            'payment_url' => $remote['invoiceUrl'] ?? null,
            'bank_slip_url' => $remote['bankSlipUrl'] ?? null,
        ]);

        FinancialEvent::query()->create([
            'team_id' => $team->id,
            'invoice_id' => $invoice->id,
            'provider' => 'asaas',
            'external_event_id' => 'manual-payment-'.$paymentId,
            'type' => 'PAYMENT_CREATED',
            'status' => $remote['status'] ?? 'PENDING',
            'amount' => $data['value'],
            'title' => 'Cobrança avulsa criada',
            'description' => $data['description'],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Cobrança criada no Asaas com sucesso.');
    }

    public function refund(Request $request, Invoice $invoice, AsaasClient $asaas): RedirectResponse
    {
        abort_unless($invoice->billing_provider === 'asaas' && $invoice->external_payment_id, 422);
        abort_unless(in_array($invoice->status, ['paid', 'refund_pending'], true), 422, 'Somente cobranças pagas podem ser estornadas.');

        $data = $request->validate([
            'value' => ['nullable', 'numeric', 'min:0.01', 'max:'.$invoice->total],
            'description' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $asaas->refundPayment($invoice->external_payment_id, isset($data['value']) ? (float) $data['value'] : null, $data['description']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $invoice->update(['status' => 'refund_pending', 'refunded_at' => now()]);
        FinancialEvent::query()->create([
            'team_id' => $invoice->team_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'provider' => 'asaas',
            'external_event_id' => 'manual-refund-'.$invoice->id.'-'.Str::uuid(),
            'type' => 'PAYMENT_REFUND_IN_PROGRESS',
            'status' => 'REQUESTED',
            'amount' => $data['value'] ?? $invoice->total,
            'title' => 'Estorno solicitado',
            'description' => $data['description'],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Estorno solicitado ao Asaas. A confirmação será atualizada pelo webhook.');
    }
}
