<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\AsaasClient;
use App\Services\BillingAutomationService;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $subscriptions = Subscription::query()
            ->with(['team.billingCustomer', 'product', 'plan'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'status'));
    }

    public function cancel(Subscription $subscription, BillingProviderManager $billing): RedirectResponse
    {
        if ($subscription->status === 'canceled') {
            return back()->with('warning', 'Essa assinatura já está cancelada.');
        }

        if ($subscription->external_subscription_id || $subscription->external_checkout_id) {
            try {
                $billing->cancel($subscription);
            } catch (RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'renews_at' => null,
            'access_status' => 'revoked',
            'access_reason' => 'admin_canceled',
            'access_updated_at' => now(),
        ]);

        return back()->with('success', 'Assinatura cancelada pelo administrador.');
    }

    public function storeForCustomer(
        Request $request,
        Team $team,
        BillingProviderManager $billing,
        AsaasClient $asaas,
        BillingAutomationService $automation,
    ): RedirectResponse
    {
        $data = $request->validate([
            'product_plan_id' => ['required', 'integer', Rule::exists('product_plans', 'id')->where('status', 'active')],
            'seats' => ['required', 'integer', 'min:1', 'max:500'],
            'first_due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $plan = ProductPlan::query()->with('product')->findOrFail($data['product_plan_id']);
        $seats = (int) $data['seats'];
        $customer = $team->billingCustomer;

        if ($plan->product->status !== 'active') {
            return back()->withInput()->with('error', 'O produto desse plano não está ativo.');
        }

        if ($seats < $plan->minimum_seats || ($plan->maximum_seats && $seats > $plan->maximum_seats)) {
            return back()->withInput()->with('error', 'A quantidade de acessos não é permitida por esse plano.');
        }

        if (! $customer || blank($customer->tax_id)) {
            return back()->withInput()->with('warning', 'Informe o CPF ou CNPJ do cliente antes de criar a assinatura.');
        }

        if (! $billing->configured()) {
            return back()->withInput()->with('error', 'A integração com o Asaas ainda não está configurada.');
        }

        if ($team->subscriptions()->where('product_id', $plan->product_id)->whereIn('status', ['pending', 'active', 'trialing', 'past_due'])->exists()) {
            return back()->withInput()->with('warning', 'Este cliente já possui uma assinatura vigente para o produto selecionado.');
        }

        if ($customer->billing_provider !== 'asaas' || ! $customer->external_customer_id) {
            try {
                $remoteCustomer = $billing->syncCustomer($customer);
                $customer->update([
                    'billing_provider' => $remoteCustomer['provider'],
                    'external_customer_id' => $remoteCustomer['id'],
                    'synced_at' => now(),
                ]);
            } catch (RuntimeException $exception) {
                return back()->withInput()->with('error', $exception->getMessage());
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

            return back()->withInput()->with('error', $exception->getMessage());
        }

        try {
            $payments = $asaas->subscriptionPayments($remoteId);
            $payment = collect($payments['data'] ?? [])->first();

            if (is_array($payment) && filled($payment['id'] ?? null)) {
                $invoice = $this->storeFirstInvoice($subscription, $payment);
                $automation->notifyInvoiceCreated($subscription, $invoice);
            }
        } catch (RuntimeException) {
            // The webhook/reconciliation flow will import and notify the first payment.
        }

        return back()->with('success', 'Assinatura criada. O Asaas gerará as cobranças automaticamente e o cliente foi avisado da primeira fatura.');
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
}
