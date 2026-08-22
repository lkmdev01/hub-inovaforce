<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()->with('plans')->withCount('subscriptions')->get();

        return view('admin.products.index', compact('products'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
            'fiscal_enabled' => ['nullable', 'boolean'],
            'municipal_service_id' => ['nullable', 'string', 'max:100'],
            'municipal_service_code' => ['nullable', 'string', 'max:50'],
            'municipal_service_name' => ['nullable', 'string', 'max:255'],
            'fiscal_service_description' => ['nullable', 'string', 'max:2000'],
            'fiscal_observations' => ['nullable', 'string', 'max:1000'],
            'fiscal_deductions' => ['nullable', 'numeric', 'min:0'],
            'fiscal_effective_period' => ['nullable', 'in:ON_PAYMENT_CONFIRMATION,ON_PAYMENT_DUE_DATE,ON_DUE_DATE_MONTH,ON_NEXT_MONTH'],
            'tax_retain_iss' => ['nullable', 'boolean'],
            'tax_iss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_csll' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_inss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_ir' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_pis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'provisioning_webhook_url' => ['nullable', 'url', 'max:2000'],
            'provisioning_webhook_secret' => ['nullable', 'string', 'min:32', 'max:255'],
        ]);

        $product->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'fiscal_enabled' => $request->boolean('fiscal_enabled'),
            'municipal_service_id' => $data['municipal_service_id'] ?? null,
            'municipal_service_code' => $data['municipal_service_code'] ?? null,
            'municipal_service_name' => $data['municipal_service_name'] ?? null,
            'fiscal_service_description' => $data['fiscal_service_description'] ?? null,
            'fiscal_observations' => $data['fiscal_observations'] ?? null,
            'fiscal_deductions' => $data['fiscal_deductions'] ?? 0,
            'fiscal_effective_period' => $data['fiscal_effective_period'] ?? 'ON_PAYMENT_CONFIRMATION',
            'fiscal_taxes' => [
                'retainIss' => $request->boolean('tax_retain_iss'),
                'iss' => (float) ($data['tax_iss'] ?? 0),
                'cofins' => (float) ($data['tax_cofins'] ?? 0),
                'csll' => (float) ($data['tax_csll'] ?? 0),
                'inss' => (float) ($data['tax_inss'] ?? 0),
                'ir' => (float) ($data['tax_ir'] ?? 0),
                'pis' => (float) ($data['tax_pis'] ?? 0),
            ],
            'provisioning_webhook_url' => $data['provisioning_webhook_url'] ?? null,
            'provisioning_webhook_secret' => filled($data['provisioning_webhook_secret'] ?? null)
                ? $data['provisioning_webhook_secret']
                : $product->provisioning_webhook_secret,
        ]);

        return back()->with('success', 'Produto atualizado.');
    }

    public function updatePlan(Request $request, ProductPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1'],
            'abacatepay_product_id' => ['nullable', 'string', 'max:255'],
        ]);
        $plan->update($data);

        return back()->with('success', 'Plano atualizado.');
    }
}
