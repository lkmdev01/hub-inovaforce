<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AutomationAlert;
use App\Models\FinancialEvent;
use App\Models\Invoice;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CustomerAccessInvitation;
use App\Services\AsaasClient;
use App\Services\BillingAutomationService;
use App\Services\BillingProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class FinanceOperationsController extends Controller
{
    public function storeCustomer(Request $request, CreateTeam $createTeam, BillingProviderManager $billing): JsonResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:30'],
        ]);

        [$team, $customer] = DB::transaction(function () use ($data, $createTeam) {
            $user = User::query()->create([
                'name' => $data['contact_name'],
                'email' => $data['email'],
                'password' => Str::password(64),
            ]);
            $team = $createTeam->handle($user, $data['company_name']);
            $customer = $team->billingCustomer()->create([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'tax_id' => $data['tax_id'] ?? null,
                'cellphone' => $data['cellphone'] ?? null,
            ]);

            return [$team, $customer];
        });

        $owner = $team->members()->where('users.email', $data['email'])->firstOrFail();
        $owner->notify(new CustomerAccessInvitation(Password::broker()->createToken($owner), $team->name));

        $message = 'Cliente cadastrado e convite de acesso enviado.';
        if ($billing->configured() && filled($customer->tax_id)) {
            try {
                $remote = $billing->syncCustomer($customer);
                $customer->update([
                    'billing_provider' => $remote['provider'],
                    'external_customer_id' => $remote['id'],
                    'synced_at' => now(),
                ]);
                $message = 'Cliente cadastrado, sincronizado e convidado.';
            } catch (RuntimeException $exception) {
                $message = 'Cliente cadastrado, mas a sincronização ficou pendente: '.$exception->getMessage();
            }
        }

        $this->audit($request, 'internal.finance.customers.store', $team, $customer);

        return response()->json(['message' => $message, 'customer' => ['id' => $customer->id, 'team_slug' => $team->slug]], 201);
    }

    public function syncCustomer(Request $request, Team $team, BillingProviderManager $billing): JsonResponse
    {
        $customer = $team->billingCustomer;
        abort_unless($customer !== null, 404, 'Cliente financeiro não encontrado.');
        abort_if(blank($customer->tax_id), 422, 'Informe o CPF ou CNPJ no Hub antes de sincronizar.');

        try {
            $remote = $billing->syncCustomer($customer);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $customer->update([
            'billing_provider' => $remote['provider'],
            'external_customer_id' => $remote['id'],
            'synced_at' => now(),
        ]);
        $this->audit($request, 'internal.finance.customers.sync', $team, $customer);

        return response()->json(['message' => 'Cliente sincronizado com o '.$billing->label().'.']);
    }

    public function storePayment(Request $request, Team $team, AsaasClient $asaas): JsonResponse
    {
        $customer = $team->billingCustomer;
        abort_unless($customer?->external_customer_id && $customer->billing_provider === 'asaas', 422, 'Sincronize o cliente com o Asaas antes de criar uma cobrança.');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'billing_type' => ['required', Rule::in(['UNDEFINED', 'PIX', 'BOLETO', 'CREDIT_CARD'])],
            'value' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $remote = $asaas->createPayment($customer, [
                'billingType' => $data['billing_type'],
                'value' => (float) $data['value'],
                'dueDate' => $data['due_date'],
                'description' => $data['description'],
                'externalReference' => 'institutional-one-off-'.$team->id.'-'.Str::uuid(),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
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
            'external_event_id' => 'institutional-payment-'.$paymentId,
            'type' => 'PAYMENT_CREATED',
            'status' => $remote['status'] ?? 'PENDING',
            'amount' => $data['value'],
            'title' => 'Cobrança avulsa criada',
            'description' => $data['description'],
            'occurred_at' => now(),
        ]);
        $this->audit($request, 'internal.finance.payments.store', $team, $invoice);

        return response()->json(['message' => 'Cobrança criada no Asaas.', 'invoice' => ['id' => $invoice->id, 'number' => $invoice->number]], 201);
    }

    public function storeSubscription(
        Request $request,
        Team $team,
        BillingProviderManager $billing,
        AsaasClient $asaas,
        BillingAutomationService $automation,
    ): JsonResponse {
        $data = $request->validate([
            'product_plan_id' => ['required', 'integer', Rule::exists('product_plans', 'id')->where('status', 'active')],
            'seats' => ['required', 'integer', 'min:1', 'max:500'],
            'first_due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $plan = ProductPlan::query()->with('product')->findOrFail((int) $data['product_plan_id']);
        $seats = (int) $data['seats'];
        $customer = $team->billingCustomer;

        abort_if($plan->product->status !== 'active', 422, 'O produto desse plano não está ativo.');
        abort_if($seats < $plan->minimum_seats || ($plan->maximum_seats && $seats > $plan->maximum_seats), 422, 'A quantidade de acessos não é permitida por esse plano.');
        abort_unless($customer && filled($customer->tax_id), 422, 'Informe o CPF ou CNPJ do cliente antes de criar a assinatura.');
        abort_unless($billing->configured(), 422, 'A integração com o Asaas ainda não está configurada.');
        abort_if($team->subscriptions()->where('product_id', $plan->product_id)->whereIn('status', ['pending', 'active', 'trialing', 'past_due'])->exists(), 422, 'Este cliente já possui uma assinatura vigente para o produto selecionado.');

        if ($customer->billing_provider !== 'asaas' || ! $customer->external_customer_id) {
            try {
                $remoteCustomer = $billing->syncCustomer($customer);
                $customer->update([
                    'billing_provider' => $remoteCustomer['provider'],
                    'external_customer_id' => $remoteCustomer['id'],
                    'synced_at' => now(),
                ]);
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        $subscription = $team->subscriptions()->create([
            'product_id' => $plan->product_id,
            'product_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'pending',
            'access_status' => 'pending',
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->totalForSeats($seats),
            'seats' => $seats,
            'billing_provider' => 'asaas',
            'renews_at' => $data['first_due_date'],
        ]);

        try {
            $remote = $asaas->createSubscription($customer->fresh(), $plan, $subscription, $data['first_due_date']);
            $remoteId = (string) ($remote['id'] ?? '');
            if ($remoteId === '') {
                throw new RuntimeException('O Asaas não retornou o identificador da assinatura.');
            }
            $subscription->update(['external_subscription_id' => $remoteId]);
        } catch (RuntimeException $exception) {
            $subscription->delete();

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        try {
            $payments = $asaas->subscriptionPayments($remoteId);
            $paymentList = $payments['data'] ?? null;
            $payment = is_array($paymentList) ? ($paymentList[array_key_first($paymentList)] ?? null) : null;
            if (is_array($payment) && filled($payment['id'] ?? null)) {
                $invoice = $this->storeFirstInvoice($subscription, $payment);
                $automation->notifyInvoiceCreated($subscription, $invoice);
            }
        } catch (RuntimeException) {
            // A conciliação e os webhooks importarão a primeira cobrança.
        }

        $this->audit($request, 'internal.finance.subscriptions.store', $team, $subscription);

        return response()->json(['message' => 'Assinatura criada no Asaas.', 'subscription' => ['id' => $subscription->id]], 201);
    }

    public function cancelSubscription(Request $request, Subscription $subscription, BillingProviderManager $billing): JsonResponse
    {
        abort_if($subscription->status === 'canceled', 422, 'Essa assinatura já está cancelada.');

        try {
            if ($subscription->external_subscription_id || $subscription->external_checkout_id) {
                $billing->cancel($subscription);
            }
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'renews_at' => null,
            'access_status' => 'revoked',
            'access_reason' => 'institutional_admin_canceled',
            'access_updated_at' => now(),
        ]);
        $this->audit($request, 'internal.finance.subscriptions.cancel', $subscription->team, $subscription);

        return response()->json(['message' => 'Assinatura cancelada.']);
    }

    public function refundInvoice(Request $request, Invoice $invoice, AsaasClient $asaas): JsonResponse
    {
        abort_unless($invoice->billing_provider === 'asaas' && $invoice->external_payment_id, 422, 'Cobrança sem vínculo válido com o Asaas.');
        abort_unless($invoice->status === 'paid', 422, 'Somente cobranças pagas podem ser estornadas.');

        $data = $request->validate([
            'value' => ['nullable', 'numeric', 'min:0.01', 'max:'.$invoice->total],
            'description' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $asaas->refundPayment($invoice->external_payment_id, isset($data['value']) ? (float) $data['value'] : null, $data['description']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $invoice->update(['status' => 'refund_pending', 'refunded_at' => now()]);
        FinancialEvent::query()->create([
            'team_id' => $invoice->team_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'provider' => 'asaas',
            'external_event_id' => 'institutional-refund-'.$invoice->id.'-'.Str::uuid(),
            'type' => 'PAYMENT_REFUND_IN_PROGRESS',
            'status' => 'REQUESTED',
            'amount' => $data['value'] ?? $invoice->total,
            'title' => 'Estorno solicitado pelo painel institucional',
            'description' => $data['description'],
            'occurred_at' => now(),
        ]);
        $this->audit($request, 'internal.finance.invoices.refund', $invoice->team, $invoice);

        return response()->json(['message' => 'Estorno solicitado ao Asaas.']);
    }

    public function resolveAlert(Request $request, AutomationAlert $alert): JsonResponse
    {
        abort_if($alert->status === 'resolved', 422, 'Esse alerta já foi resolvido.');
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->audit($request, 'internal.finance.alerts.resolve', $alert->team, $alert);

        return response()->json(['message' => 'Alerta marcado como resolvido.']);
    }

    /** @param array<string, mixed> $payment */
    private function storeFirstInvoice(Subscription $subscription, array $payment): Invoice
    {
        $paymentId = (string) $payment['id'];
        $amount = (float) ($payment['value'] ?? $subscription->amount);

        return Invoice::query()->updateOrCreate(
            ['billing_provider' => 'asaas', 'external_payment_id' => $paymentId],
            [
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'kind' => 'subscription',
                'number' => 'ASAAS-'.Str::upper(Str::after($paymentId, 'pay_')),
                'description' => $subscription->product->name.' — '.$subscription->plan_name,
                'status' => 'open',
                'currency' => 'BRL',
                'subtotal' => $amount,
                'total' => $amount,
                'issued_at' => $payment['dateCreated'] ?? today(),
                'due_at' => $payment['dueDate'] ?? $subscription->renews_at ?? today(),
                'payment_url' => $payment['invoiceUrl'] ?? null,
                'bank_slip_url' => $payment['bankSlipUrl'] ?? null,
            ],
        );
    }

    private function audit(Request $request, string $action, ?Team $team, object $subject): void
    {
        AuditLog::query()->create([
            'user_id' => null,
            'team_id' => $team?->id,
            'action' => $action,
            'subject_type' => method_exists($subject, 'getMorphClass') ? $subject->getMorphClass() : $subject::class,
            'subject_id' => method_exists($subject, 'getKey') ? $subject->getKey() : null,
            'metadata' => [
                'source' => 'institutional-dashboard',
                'client' => $request->header('X-Inova-Client'),
                'request_id' => $request->header('X-Inova-Request-Id'),
                'changed_fields' => collect($request->all())->except(['tax_id'])->keys()->values()->all(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
