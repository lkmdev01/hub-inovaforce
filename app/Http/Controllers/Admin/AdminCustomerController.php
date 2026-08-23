<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\BillingCustomer;
use App\Models\CustomerGroup;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

class AdminCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $groupId = $request->integer('group');
        $customers = BillingCustomer::query()
            ->with(['group', 'team' => fn ($query) => $query->with(['members'])->withCount('subscriptions')])
            ->when($groupId, fn ($query) => $query->where('customer_group_id', $groupId))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $groups = CustomerGroup::query()->withCount('customers')->orderBy('name')->get();

        return view('admin.customers.index', compact('customers', 'groups', 'groupId', 'search'));
    }

    public function store(Request $request, CreateTeam $createTeam, BillingProviderManager $billing): RedirectResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'tax_id' => ['required', 'string', 'max:20'],
            'cellphone' => ['required', 'string', 'max:30'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
        ]);

        [$team, $customer] = DB::transaction(function () use ($data, $createTeam) {
            $user = User::query()->create([
                'name' => $data['contact_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);
            $team = $createTeam->handle($user, $data['company_name']);
            $customer = $team->billingCustomer()->create([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'tax_id' => $data['tax_id'],
                'cellphone' => $data['cellphone'],
                'zip_code' => $data['zip_code'],
                'customer_group_id' => $data['customer_group_id'] ?? null,
            ]);

            return [$team, $customer];
        });

        if ($billing->configured()) {
            try {
                $remote = $billing->syncCustomer($customer);
                $customer->update([
                    'billing_provider' => $remote['provider'],
                    'external_customer_id' => $remote['id'],
                    'synced_at' => now(),
                ]);
            } catch (RuntimeException $exception) {
                return redirect()->route('admin.customers.show', $team)
                    ->with('warning', 'Cliente local criado, mas a sincronização falhou: '.$exception->getMessage());
            }
        }

        return redirect()->route('admin.customers.show', $team)->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function show(Team $team): View
    {
        $team->load(['billingCustomer.group', 'members', 'subscriptions.product', 'subscriptions.plan', 'invoices', 'fiscalDocuments', 'financialEvents']);
        $groups = CustomerGroup::query()->where('active', true)->orderBy('name')->get();

        return view('admin.customers.show', compact('team', 'groups'));
    }

    public function sync(Team $team, BillingProviderManager $billing): RedirectResponse
    {
        $customer = $team->billingCustomer;
        abort_unless($customer !== null, 404);

        try {
            $remote = $billing->syncCustomer($customer);
            $customer->update([
                'billing_provider' => $remote['provider'],
                'external_customer_id' => $remote['id'],
                'synced_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cliente sincronizado com o '.$billing->label().'.');
    }

    public function assignGroup(Request $request, Team $team, BillingProviderManager $billing): RedirectResponse
    {
        $data = $request->validate([
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
        ]);
        $customer = $team->billingCustomer;
        abort_unless($customer !== null, 404);

        $customer->update(['customer_group_id' => $data['customer_group_id'] ?? null]);

        if ($billing->configured() && $customer->billing_provider === 'asaas') {
            try {
                $billing->syncCustomer($customer->fresh('group'));
                $customer->update(['synced_at' => now()]);
            } catch (RuntimeException $exception) {
                return back()->with('warning', 'Grupo salvo no Hub, mas não sincronizado: '.$exception->getMessage());
            }
        }

        return back()->with('success', 'Grupo do cliente atualizado.');
    }
}
