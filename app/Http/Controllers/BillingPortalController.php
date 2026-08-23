<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class BillingPortalController extends Controller
{
    public function dashboard(Team $current_team): View
    {
        $subscriptions = $current_team->subscriptions()->with(['product', 'plan', 'pendingPlan'])->latest()->get();
        $invoices = $current_team->invoices()->with('subscription.product')->latest('issued_at')->take(5)->get();

        return view('dashboard', compact('current_team', 'subscriptions', 'invoices'));
    }

    public function subscriptions(Team $current_team): View
    {
        $subscriptions = $current_team->subscriptions()->with(['product', 'plan', 'pendingPlan', 'product.plans'])->latest()->get();

        return view('portal.subscriptions', compact('current_team', 'subscriptions'));
    }

    public function invoices(Team $current_team): View
    {
        $invoices = $current_team->invoices()->with('subscription.product')->latest('issued_at')->get();

        return view('portal.invoices', compact('current_team', 'invoices'));
    }

    public function invoice(Team $current_team, Invoice $invoice): View
    {
        abort_unless($invoice->team_id === $current_team->id, 404);

        return view('portal.invoice', [
            'invoice' => $invoice->load(['subscription.product', 'fiscalDocuments', 'financialEvents']),
            'current_team' => $current_team,
        ]);
    }

    public function products(Team $current_team): View
    {
        $products = Product::query()->with('plans')->where('status', 'active')->get();
        $subscribedProductIds = $current_team->subscriptions()->whereIn('status', ['pending', 'active', 'trialing'])->pluck('product_id');

        return view('portal.products', compact('current_team', 'products', 'subscribedProductIds'));
    }

    public function changePlan(Request $request, Team $current_team, Subscription $subscription, BillingProviderManager $billing): RedirectResponse
    {
        abort_unless($subscription->team_id === $current_team->id, 404);

        if (! in_array($subscription->status, ['active', 'trialing', 'past_due'], true)) {
            return back()->with('warning', 'Aguarde a ativação da assinatura antes de alterar o plano.');
        }

        $data = $request->validate([
            'product_plan_id' => ['required', 'integer', 'exists:product_plans,id'],
            'seats' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $plan = ProductPlan::query()
            ->where('product_id', $subscription->product_id)
            ->whereKey($data['product_plan_id'])
            ->firstOrFail();

        if ($subscription->external_subscription_id) {
            try {
                $billing->changePlan($subscription, $plan);
            } catch (RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }

            $subscription->update(['pending_product_plan_id' => $plan->id, 'seats' => $data['seats']]);

            return back()->with('success', 'Troca de plano agendada para o próximo ciclo.');
        }

        $subscription->update([
            'product_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->price,
            'seats' => $data['seats'],
        ]);

        return back()->with('success', 'Assinatura atualizada com sucesso.');
    }

    public function toggleSubscription(Team $current_team, Subscription $subscription, BillingProviderManager $billing): RedirectResponse
    {
        abort_unless($subscription->team_id === $current_team->id, 404);

        if ($subscription->status === 'pending') {
            return back()->with('warning', 'O checkout pendente expira automaticamente se não for pago.');
        }

        if ($subscription->status === 'canceled') {
            return redirect()->route('products.index', ['current_team' => $current_team])
                ->with('warning', 'Assinaturas canceladas não podem ser reativadas. Inicie uma nova contratação.');
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
            'access_reason' => 'customer_canceled',
            'access_updated_at' => now(),
        ]);

        return back()->with('success', 'Assinatura cancelada. O acesso foi encerrado imediatamente.');
    }

    public function issueInvoice(Team $current_team, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->team_id === $current_team->id, 404);

        $invoice = $current_team->invoices()->create([
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(6)),
            'status' => 'open',
            'currency' => 'BRL',
            'subtotal' => $subscription->amount,
            'total' => $subscription->amount,
            'issued_at' => today(),
            'due_at' => today()->addDays(7),
        ]);

        return redirect()->route('invoices.show', ['current_team' => $current_team, 'invoice' => $invoice])
            ->with('success', 'Fatura gerada com sucesso.');
    }
}
