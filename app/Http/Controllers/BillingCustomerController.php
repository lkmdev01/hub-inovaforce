<?php

namespace App\Http\Controllers;

use App\Models\BillingCustomer;
use App\Models\Team;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingCustomerController extends Controller
{
    public function show(Team $current_team, Request $request, BillingProviderManager $billingProvider): View
    {
        $customer = $current_team->billingCustomer ?? new BillingCustomer([
            'name' => $current_team->name,
            'email' => $request->user()->email,
        ]);

        return view('portal.customer', compact('current_team', 'customer', 'billingProvider'));
    }

    public function update(Team $current_team, Request $request, BillingProviderManager $billingProvider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'tax_id' => ['required', 'string', 'max:20'],
            'cellphone' => ['required', 'string', 'max:30'],
            'zip_code' => ['nullable', 'string', 'max:10'],
        ]);

        $customer = $current_team->billingCustomer()->updateOrCreate([], $data);

        if (! $billingProvider->configured()) {
            return back()->with('warning', 'Dados salvos. Adicione a chave do Asaas para ativar a sincronização.');
        }

        try {
            $remote = $billingProvider->syncCustomer($customer);
            $customer->update([
                'billing_provider' => $remote['provider'],
                'external_customer_id' => $remote['id'],
                'synced_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cliente cadastrado e sincronizado com o '.$billingProvider->label().'.');
    }
}
