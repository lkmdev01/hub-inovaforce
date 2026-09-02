<x-layouts::app :title="__('Visão geral')">
    @php
        $isClientPreview = request()->attributes->has('clientPreviewTeam');
    @endphp

    @unless ($isClientPreview)
        <livewire:pages::teams.pending-invitations-modal />
    @endunless

    @php
        $activeSubscriptions = $subscriptions->whereIn('status', ['active', 'trialing']);
        $monthlyTotal = $activeSubscriptions->sum(fn ($subscription) => $subscription->monthlyEquivalentAmount());
        $openInvoices = $invoices->whereIn('status', ['open', 'overdue']);
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $isClientPreview ? 'Portal de '.$current_team->name : 'Olá, '.str(auth()->user()->name)->before(' ').'.' }}</h1>
                <p class="mt-2 text-zinc-500 dark:text-zinc-400">Aqui está o resumo dos serviços da {{ $current_team->name }}.</p>
            </div>
            <a href="{{ route('products.index') }}" wire:navigate class="inline-flex h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-500">
                Explorar produtos
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"><flux:icon.squares-2x2 class="size-5" /></span>
                    <span class="portal-kicker">Serviços</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">{{ $activeSubscriptions->count() }}</p>
                <p class="mt-1 text-sm text-zinc-500">assinaturas ativas</p>
            </div>
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-sky-100 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400"><flux:icon.credit-card class="size-5" /></span>
                    <span class="portal-kicker">Mensalidade</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">R$ {{ number_format($monthlyTotal, 2, ',', '.') }}</p>
                <p class="mt-1 text-sm text-zinc-500">custo mensal estimado</p>
            </div>
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><flux:icon.document-text class="size-5" /></span>
                    <span class="portal-kicker">Faturamento</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">{{ $openInvoices->count() }}</p>
                <p class="mt-1 text-sm text-zinc-500">faturas aguardando pagamento</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
            <section class="portal-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-semibold">Seus produtos</h2>
                        <p class="mt-0.5 text-sm text-zinc-500">Serviços contratados pela sua empresa</p>
                    </div>
                    <a href="{{ route('subscriptions.index') }}" wire:navigate class="text-sm font-semibold text-violet-600 hover:text-violet-500">Gerenciar</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($subscriptions->take(4) as $subscription)
                        <div class="flex items-center gap-4 p-5">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-sm font-bold text-white shadow-sm">{{ str($subscription->product->name)->substr(0, 2)->upper() }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-semibold">{{ $subscription->product->name }}</p>
                                    <x-portal-status :status="$subscription->status" />
                                </div>
                                <p class="mt-1 text-sm text-zinc-500">Plano {{ $subscription->plan_name }} · {{ $subscription->seats }} {{ $subscription->seats === 1 ? 'acesso' : 'acessos' }}</p>
                            </div>
                            <div class="hidden text-right sm:block">
                                <p class="font-semibold">R$ {{ number_format($subscription->amount, 2, ',', '.') }}</p>
                                    <p class="text-xs text-zinc-500">/{{ mb_strtolower(App\Models\ProductPlan::CYCLES[$subscription->billing_cycle] ?? $subscription->billing_cycle) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhum produto contratado ainda.</div>
                    @endforelse
                </div>
            </section>

            <section class="portal-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold">Próximos passos</h2>
                    <p class="mt-0.5 text-sm text-zinc-500">Mantenha sua conta em dia</p>
                </div>
                <div class="space-y-1 p-3">
                    <a href="{{ route('invoices.index') }}" wire:navigate class="portal-action">
                        <span class="portal-icon bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10"><flux:icon.banknotes class="size-5" /></span>
                        <span class="flex-1"><strong class="block text-sm">Consultar faturas</strong><span class="text-xs text-zinc-500">Pagamentos e comprovantes</span></span>
                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                    </a>
                    <a href="{{ route('subscriptions.index') }}" wire:navigate class="portal-action">
                        <span class="portal-icon bg-sky-100 text-sky-600 dark:bg-sky-500/10"><flux:icon.adjustments-horizontal class="size-5" /></span>
                        <span class="flex-1"><strong class="block text-sm">Ajustar seus planos</strong><span class="text-xs text-zinc-500">Plano, ciclo e usuários</span></span>
                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                    </a>
                    <a href="{{ $isClientPreview ? route('customer.show') : route('teams.edit', $current_team) }}" wire:navigate class="portal-action">
                        <span class="portal-icon bg-amber-100 text-amber-600 dark:bg-amber-500/10">
                            @if ($isClientPreview)
                                <flux:icon.building-office class="size-5" />
                            @else
                                <flux:icon.users class="size-5" />
                            @endif
                        </span>
                        <span class="flex-1"><strong class="block text-sm">{{ $isClientPreview ? 'Consultar cadastro' : 'Gerenciar equipe' }}</strong><span class="text-xs text-zinc-500">{{ $isClientPreview ? 'Dados da empresa em modo leitura' : 'Membros e permissões' }}</span></span>
                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
