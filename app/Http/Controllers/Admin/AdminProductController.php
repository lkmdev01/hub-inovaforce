<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()->with('plans')->withCount('subscriptions')->get();

        return view('admin.products.index', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', 'unique:products,slug'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,active'],
            'accent' => ['required', 'in:violet,sky,fuchsia,emerald,amber,rose'],
            'features' => ['nullable', 'string', 'max:2000'],
            'plan_name' => ['required', 'string', 'max:100'],
            'billing_cycle' => ['required', Rule::in(array_keys(ProductPlan::CYCLES))],
            'billing_type' => ['required', Rule::in(array_keys(ProductPlan::BILLING_TYPES))],
            'price' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'pricing_model' => ['required', Rule::in(array_keys(ProductPlan::PRICING_MODELS))],
            'minimum_seats' => ['required', 'integer', 'min:1', 'max:500'],
            'maximum_seats' => ['nullable', 'integer', 'min:1', 'max:500', 'gte:minimum_seats'],
        ]);

        $product = DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                'name' => $data['name'],
                'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name']),
                'description' => $data['description'],
                'status' => $data['status'],
                'accent' => $data['accent'],
                'features' => $this->features($data['features'] ?? null),
            ]);

            $product->plans()->create([
                'name' => $data['plan_name'],
                'status' => 'active',
                'billing_cycle' => $data['billing_cycle'],
                'billing_type' => $data['billing_type'],
                'price' => $data['price'],
                'pricing_model' => $data['pricing_model'],
                'minimum_seats' => $data['minimum_seats'],
                'maximum_seats' => $data['maximum_seats'] ?? null,
            ]);

            return $product;
        });

        return redirect(route('admin.products.index').'#produto-'.$product->id)
            ->with('success', 'Produto e primeiro plano criados com sucesso.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('products', 'slug')->ignore($product)],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,active,archived'],
            'accent' => ['required', 'in:violet,sky,fuchsia,emerald,amber,rose'],
            'features' => ['nullable', 'string', 'max:2000'],
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
            'slug' => $data['slug'],
            'description' => $data['description'],
            'status' => $data['status'],
            'accent' => $data['accent'],
            'features' => $this->features($data['features'] ?? null),
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

    public function storePlan(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatePlan($request, $product);
        $product->plans()->create($data);

        return back()->with('success', 'Plano adicionado ao produto.');
    }

    public function updatePlan(Request $request, ProductPlan $plan): RedirectResponse
    {
        $data = $this->validatePlan($request, $plan->product, $plan);
        $plan->update($data);

        return back()->with('success', 'Plano atualizado.');
    }

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, Product $product, ?ProductPlan $plan = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_plans', 'name')
                    ->where(fn ($query) => $query
                        ->where('product_id', $product->id)
                        ->where('billing_cycle', $request->input('billing_cycle')))
                    ->ignore($plan),
            ],
            'status' => ['required', 'in:active,inactive'],
            'billing_cycle' => ['required', Rule::in(array_keys(ProductPlan::CYCLES))],
            'billing_type' => ['required', Rule::in(array_keys(ProductPlan::BILLING_TYPES))],
            'price' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'pricing_model' => ['required', Rule::in(array_keys(ProductPlan::PRICING_MODELS))],
            'minimum_seats' => ['required', 'integer', 'min:1', 'max:500'],
            'maximum_seats' => ['nullable', 'integer', 'min:1', 'max:500', 'gte:minimum_seats'],
        ]);
    }

    /** @return array<int, string> */
    private function features(?string $features): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $features) ?: [])
            ->map(fn (string $feature) => trim($feature))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produto';
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
