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
        $products = Product::query()
            ->with(['plans' => fn ($query) => $query->where('status', 'active')])
            ->where('status', 'active')
            ->get();
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
            ->where('status', 'active')
            ->whereKey($data['product_plan_id'])
            ->firstOrFail();

        if ($data['seats'] < $plan->minimum_seats || $data['seats'] > ($plan->maximum_seats ?? 500)) {
            return back()->withErrors(['seats' => 'A quantidade de licenças não é permitida para o plano escolhido.']);
        }

        if ($subscription->external_subscription_id) {
            try {
                $billing->changePlan($subscription, $plan, $data['seats']);
            } catch (RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }

            $subscription->update([
                'pending_product_plan_id' => $plan->id,
                'pending_seats' => $data['seats'],
            ]);

            return back()->with('success', 'Troca de plano agendada para o próximo ciclo.');
        }

        $subscription->update([
            'product_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->totalForSeats($data['seats']),
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

        if ($subscription->renews_at?->isFuture()) {
            $subscription->update([
                'cancel_at_period_end' => true,
                'cancel_scheduled_at' => now(),
                'access_reason' => 'customer_cancel_scheduled',
            ]);

            return back()->with('success', 'Renovação desativada. O acesso permanece até '.$subscription->renews_at->format('d/m/Y').'.');
        }

        $subscription->update([
            'status' => 'canceled', 'canceled_at' => now(), 'renews_at' => null,
            'access_status' => 'revoked', 'access_reason' => 'customer_canceled',
            'access_updated_at' => now(), 'cancel_at_period_end' => false,
        ]);

        return back()->with('success', 'Assinatura cancelada. O acesso foi encerrado imediatamente.');
    }
}
