<x-layouts::app :title="__('Administração')">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">Central administrativa</p>
                <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">Visão geral do negócio</h1>
                <p class="mt-2 text-zinc-500 dark:text-zinc-400">Clientes, assinaturas e faturamento em um só lugar.</p>
            </div>
            <a href="{{ route('admin.customers.index') }}#novo-cliente" wire:navigate class="inline-flex h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-500">
                Cadastrar cliente
            </a>
        </div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))
                <x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>
            @endif
        @endforeach

        @if (! $billingConfigured)
            <x-portal-alert type="warning">O {{ $billingProvider }} está selecionado, mas a chave da API ainda não foi configurada. Clientes e assinaturas continuam salvos no Hub, porém a sincronização e os novos checkouts ficam indisponíveis.</x-portal-alert>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"><flux:icon.users class="size-5" /></span>
                    <span class="portal-kicker">Base</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">{{ $metrics['customers'] }}</p>
                <p class="mt-1 text-sm text-zinc-500">clientes cadastrados</p>
            </div>
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"><flux:icon.squares-2x2 class="size-5" /></span>
                    <span class="portal-kicker">Recorrência</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">{{ $metrics['active_subscriptions'] }}</p>
                <p class="mt-1 text-sm text-zinc-500">assinaturas ativas</p>
            </div>
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-sky-100 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400"><flux:icon.banknotes class="size-5" /></span>
                    <span class="portal-kicker">MRR estimado</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">R$ {{ number_format($metrics['mrr'], 2, ',', '.') }}</p>
                <p class="mt-1 text-sm text-zinc-500">receita mensal recorrente</p>
            </div>
            <div class="portal-card p-5">
                <div class="flex items-start justify-between">
                    <span class="portal-icon bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><flux:icon.credit-card class="size-5" /></span>
                    <span class="portal-kicker">Recebido no mês</span>
                </div>
                <p class="mt-7 text-3xl font-semibold tracking-tight">R$ {{ number_format($metrics['paid_this_month'], 2, ',', '.') }}</p>
                <p class="mt-1 text-sm text-zinc-500">faturas marcadas como pagas</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="portal-card p-5"><span class="portal-kicker">Inadimplência</span><p class="mt-5 text-2xl font-semibold">R$ {{ number_format($metrics['overdue_amount'], 2, ',', '.') }}</p><p class="mt-1 text-sm text-zinc-500">saldo vencido</p></div>
            <div class="portal-card p-5"><span class="portal-kicker">Churn no mês</span><p class="mt-5 text-2xl font-semibold">{{ number_format($metrics['churn_rate'], 1, ',', '.') }}%</p><p class="mt-1 text-sm text-zinc-500">{{ $metrics['canceled_this_month'] }} cancelamento(s)</p></div>
            <div class="portal-card p-5"><span class="portal-kicker">Estornos no mês</span><p class="mt-5 text-2xl font-semibold">R$ {{ number_format($metrics['refunded_this_month'], 2, ',', '.') }}</p><p class="mt-1 text-sm text-zinc-500">devoluções registradas</p></div>
            <a href="{{ route('admin.automations.index') }}" wire:navigate class="portal-card p-5 transition hover:border-violet-300"><span class="portal-kicker">Alertas</span><p class="mt-5 text-2xl font-semibold">{{ $metrics['open_alerts'] }}</p><p class="mt-1 text-sm text-zinc-500">pendências operacionais</p></a>
        </div>

        @if ($recentAlerts->isNotEmpty())
            <section class="portal-card overflow-hidden"><div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><div><h2 class="font-semibold">Atenção necessária</h2><p class="mt-0.5 text-sm text-zinc-500">Alertas criados pelas automações</p></div><a href="{{ route('admin.automations.index') }}" wire:navigate class="text-sm font-semibold text-violet-600">Ver central</a></div><div class="divide-y divide-zinc-100 dark:divide-zinc-800">@foreach($recentAlerts as $alert)<div class="flex items-center gap-3 px-5 py-4"><span class="size-2.5 rounded-full {{ in_array($alert->severity, ['error', 'critical']) ? 'bg-red-500' : 'bg-amber-500' }}"></span><div class="min-w-0 flex-1"><strong class="block text-sm">{{ $alert->title }}</strong><span class="block truncate text-xs text-zinc-500">{{ $alert->team?->name }} · {{ $alert->message }}</span></div><span class="text-xs text-zinc-400">{{ $alert->created_at->diffForHumans() }}</span></div>@endforeach</div></section>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('admin.subscriptions.index', ['status' => 'pending']) }}" wire:navigate class="portal-card flex items-center gap-4 p-5 transition hover:border-amber-300 dark:hover:border-amber-800">
                <span class="portal-icon bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><flux:icon.clock class="size-5" /></span>
                <span class="flex-1"><strong class="block">{{ $metrics['pending_checkouts'] }} checkout(s) pendente(s)</strong><span class="text-sm text-zinc-500">Aguardando a confirmação do pagamento</span></span>
                <flux:icon.chevron-right class="size-4 text-zinc-400" />
            </a>
            <a href="{{ route('admin.subscriptions.index', ['status' => 'past_due']) }}" wire:navigate class="portal-card flex items-center gap-4 p-5 transition hover:border-red-300 dark:hover:border-red-800">
                <span class="portal-icon bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400"><flux:icon.exclamation-triangle class="size-5" /></span>
                <span class="flex-1"><strong class="block">{{ $metrics['past_due'] }} assinatura(s) inadimplente(s)</strong><span class="text-sm text-zinc-500">Pagamentos que precisam de atenção</span></span>
                <flux:icon.chevron-right class="size-4 text-zinc-400" />
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="portal-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div><h2 class="font-semibold">Clientes recentes</h2><p class="mt-0.5 text-sm text-zinc-500">Últimos cadastros realizados</p></div>
                    <a href="{{ route('admin.customers.index') }}" wire:navigate class="text-sm font-semibold text-violet-600 hover:text-violet-500">Ver todos</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recentCustomers as $customer)
                        <a href="{{ route('admin.customers.show', $customer->team) }}" wire:navigate class="flex items-center gap-4 p-5 transition hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ str($customer->name)->substr(0, 2)->upper() }}</span>
                            <span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $customer->name }}</strong><span class="block truncate text-xs text-zinc-500">{{ $customer->email }}</span></span>
                            <span class="text-xs font-medium {{ $customer->synced_at ? 'text-emerald-600' : 'text-amber-600' }}">{{ $customer->synced_at ? 'Sincronizado' : 'Pendente' }}</span>
                        </a>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhum cliente cadastrado.</div>
                    @endforelse
                </div>
            </section>

            <section class="portal-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div><h2 class="font-semibold">Assinaturas recentes</h2><p class="mt-0.5 text-sm text-zinc-500">Movimentações mais recentes</p></div>
                    <a href="{{ route('admin.subscriptions.index') }}" wire:navigate class="text-sm font-semibold text-violet-600 hover:text-violet-500">Ver todas</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recentSubscriptions as $subscription)
                        <div class="flex items-center gap-4 p-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ str($subscription->product->name)->substr(0, 2)->upper() }}</span>
                            <span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $subscription->team->name }}</strong><span class="block truncate text-xs text-zinc-500">{{ $subscription->product->name }} · {{ $subscription->plan_name }}</span></span>
                            <x-portal-status :status="$subscription->status" />
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhuma assinatura cadastrada.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
