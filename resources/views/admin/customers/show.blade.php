<x-layouts::app :title="$team->name">
    @php($customer = $team->billingCustomer)
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('admin.customers.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-violet-600 hover:text-violet-500"><flux:icon.chevron-left class="size-4" /> Clientes</a>
                <h1 class="text-3xl font-semibold tracking-tight">{{ $team->name }}</h1>
                <p class="mt-2 text-zinc-500">Ficha administrativa e histórico do cliente.</p>
            </div>
            @if ($customer)
                <form method="POST" action="{{ route('admin.customers.sync', $team) }}">
                    @csrf
                    <button class="inline-flex h-10 items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 text-sm font-semibold shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">Sincronizar com Asaas</button>
                </form>
            @endif
        </div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))
                <x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>
            @endif
        @endforeach

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="portal-card p-5"><span class="portal-kicker">Assinaturas</span><p class="mt-5 text-3xl font-semibold">{{ $team->subscriptions->count() }}</p><p class="mt-1 text-sm text-zinc-500">{{ $team->subscriptions->whereIn('status', ['active', 'trialing'])->count() }} ativa(s)</p></div>
            <div class="portal-card p-5"><span class="portal-kicker">Acessos</span><p class="mt-5 text-3xl font-semibold">{{ $team->members->count() }}</p><p class="mt-1 text-sm text-zinc-500">usuários na empresa</p></div>
            <div class="portal-card p-5"><span class="portal-kicker">Faturas</span><p class="mt-5 text-3xl font-semibold">{{ $team->invoices->count() }}</p><p class="mt-1 text-sm text-zinc-500">{{ $team->invoices->whereIn('status', ['open', 'overdue'])->count() }} em aberto</p></div>
            <div class="portal-card p-5"><span class="portal-kicker">Mensalidade</span><p class="mt-5 text-3xl font-semibold">R$ {{ number_format($team->subscriptions->whereIn('status', ['active', 'trialing'])->sum(fn ($item) => $item->billing_cycle === 'yearly' ? $item->amount / 12 : $item->amount), 2, ',', '.') }}</p><p class="mt-1 text-sm text-zinc-500">valor mensal estimado</p></div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
            <section class="portal-card p-5">
                <div class="mb-5 flex items-center justify-between"><div><h2 class="font-semibold">Dados de cobrança</h2><p class="mt-0.5 text-sm text-zinc-500">Informações usadas na integração</p></div>@if ($customer)<span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $customer->synced_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' }}">{{ $customer->synced_at ? 'Sincronizado' : 'Pendente' }}</span>@endif</div>
                @if ($customer)
                    <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-1">
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Empresa</dt><dd class="mt-1 font-medium">{{ $customer->name }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">E-mail</dt><dd class="mt-1 break-all font-medium">{{ $customer->email }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">CPF/CNPJ</dt><dd class="mt-1 font-medium">{{ $customer->tax_id ?: 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Celular</dt><dd class="mt-1 font-medium">{{ $customer->cellphone ?: 'Não informado' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Grupo</dt><dd class="mt-1 font-medium">{{ $customer->group?->name ?? 'Sem grupo' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Provedor</dt><dd class="mt-1 font-medium">{{ $customer->billing_provider ? str($customer->billing_provider)->headline() : 'Local' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Código externo</dt><dd class="mt-1 break-all font-mono text-xs">{{ $customer->external_customer_id ?: 'Ainda não gerado' }}</dd></div>
                    </dl>
                    <form method="POST" action="{{ route('admin.customers.group', $team) }}" class="mt-5 flex gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                        @csrf @method('PATCH')
                        <select name="customer_group_id" class="h-10 min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">Sem grupo</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected($customer->customer_group_id === $group->id)>{{ $group->name }}</option>@endforeach</select>
                        <button class="rounded-xl border border-zinc-300 px-4 text-sm font-semibold dark:border-zinc-700">Atualizar grupo</button>
                    </form>
                @else
                    <p class="text-sm text-zinc-500">Este time ainda não possui perfil de cobrança.</p>
                @endif
            </section>

            <section class="portal-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="font-semibold">Assinaturas</h2><p class="mt-0.5 text-sm text-zinc-500">Produtos vinculados a esta empresa</p></div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($team->subscriptions as $subscription)
                        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ str($subscription->product->name)->substr(0, 2)->upper() }}</span>
                            <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><strong>{{ $subscription->product->name }}</strong><x-portal-status :status="$subscription->status" /></div><p class="mt-1 text-sm text-zinc-500">{{ $subscription->plan_name }} · {{ $subscription->seats }} acesso(s) · {{ $subscription->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }}</p></div>
                            <div class="text-left sm:text-right"><strong>R$ {{ number_format($subscription->amount, 2, ',', '.') }}</strong><p class="text-xs text-zinc-500">Renovação {{ $subscription->renews_at?->format('d/m/Y') ?? 'não definida' }}</p></div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhuma assinatura vinculada.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="portal-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="font-semibold">Usuários com acesso</h2><p class="mt-0.5 text-sm text-zinc-500">Membros cadastrados nesta empresa</p></div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($team->members as $member)
                        <div class="flex items-center gap-3 px-5 py-4"><span class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold dark:bg-zinc-800">{{ $member->initials() }}</span><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $member->name }}</strong><span class="block truncate text-xs text-zinc-500">{{ $member->email }}</span></span><span class="text-xs font-medium text-zinc-500">{{ $member->pivot->role->label() }}</span></div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhum usuário vinculado.</div>
                    @endforelse
                </div>
            </section>

            <section class="portal-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="font-semibold">Faturas recentes</h2><p class="mt-0.5 text-sm text-zinc-500">Últimos lançamentos do cliente</p></div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($team->invoices->sortByDesc('issued_at')->take(6) as $invoice)
                        <div class="flex items-center gap-4 px-5 py-4"><div class="min-w-0 flex-1"><strong class="block text-sm">{{ $invoice->number }}</strong><span class="text-xs text-zinc-500">Vencimento {{ $invoice->due_at->format('d/m/Y') }}</span></div><x-portal-status :status="$invoice->status" /><strong class="text-sm">R$ {{ number_format($invoice->total, 2, ',', '.') }}</strong></div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhuma fatura emitida.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
