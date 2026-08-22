<x-layouts::app :title="__('Assinaturas')">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-1 text-sm font-medium text-violet-600">Serviços</p>
                <h1 class="text-3xl font-semibold tracking-tight">Assinaturas</h1>
                <p class="mt-2 text-zinc-500">Controle planos, usuários, ciclos de cobrança e renovações.</p>
            </div>
            <a href="{{ route('products.index') }}" wire:navigate class="inline-flex h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Adicionar produto</a>
        </div>

        @foreach (['success', 'warning', 'error'] as $type)
            @if (session($type))<x-portal-alert :type="$type">{{ session($type) }}</x-portal-alert>@endif
        @endforeach

        <div class="grid gap-5 lg:grid-cols-2">
            @forelse ($subscriptions as $subscription)
                <article class="portal-card overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 font-bold text-white shadow-sm">{{ str($subscription->product->name)->substr(0, 2)->upper() }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold">{{ $subscription->product->name }}</h2>
                                    <x-portal-status :status="$subscription->status" />
                                    <x-portal-status :status="$subscription->access_status" />
                                </div>
                                <p class="mt-1 text-sm text-zinc-500">{{ $subscription->product->description }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-3 gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                            <div><p class="text-xs text-zinc-500">Plano</p><p class="mt-1 text-sm font-semibold">{{ $subscription->plan_name }}</p></div>
                            <div><p class="text-xs text-zinc-500">Usuários</p><p class="mt-1 text-sm font-semibold">{{ $subscription->seats }}</p></div>
                            <div><p class="text-xs text-zinc-500">Renovação</p><p class="mt-1 text-sm font-semibold">{{ $subscription->renews_at?->format('d/m/Y') ?? '—' }}</p></div>
                        </div>

                        @if ($subscription->pendingPlan)
                            <p class="mt-3 rounded-lg bg-violet-50 px-3 py-2 text-xs font-medium text-violet-700">Mudança para {{ $subscription->pendingPlan->name }} agendada para o próximo ciclo.</p>
                        @endif

                        @if ($subscription->access_status === 'suspended')
                            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-400">O acesso está temporariamente suspenso. Consulte as faturas em aberto para regularizar.</p>
                        @endif

                        <div class="mt-5 flex items-end justify-between gap-3">
                            <div><p class="text-2xl font-semibold">R$ {{ number_format($subscription->amount, 2, ',', '.') }}</p><p class="text-xs text-zinc-500">por {{ $subscription->billing_cycle === 'yearly' ? 'ano' : 'mês' }}</p></div>
                            @if ($subscription->status === 'pending' && $subscription->checkout_url)
                                <a href="{{ $subscription->checkout_url }}" class="rounded-xl bg-violet-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-violet-500">Concluir pagamento</a>
                            @endif
                        </div>
                    </div>

                    @if (in_array($subscription->status, ['active', 'trialing', 'past_due']))
                    <details class="group border-t border-zinc-200 dark:border-zinc-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-6 py-4 text-sm font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            Gerenciar assinatura
                            <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                        </summary>
                        <div class="border-t border-zinc-100 p-6 dark:border-zinc-800">
                            <form method="POST" action="{{ route('subscriptions.update', ['subscription' => $subscription]) }}" class="grid gap-4 sm:grid-cols-3">
                                @csrf
                                @method('PATCH')
                                <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Plano e ciclo</span><select name="product_plan_id" class="rounded-xl border-zinc-300 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-900">@foreach ($subscription->product->plans as $plan)<option value="{{ $plan->id }}" @selected($subscription->product_plan_id === $plan->id)>{{ $plan->name }} · {{ $plan->billing_cycle === 'yearly' ? 'Anual' : 'Mensal' }} · R$ {{ number_format($plan->price, 2, ',', '.') }}</option>@endforeach</select></label>
                                <label class="grid gap-1.5 text-sm"><span class="font-medium">Usuários</span><input name="seats" type="number" min="1" max="500" value="{{ $subscription->seats }}" class="rounded-xl border-zinc-300 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
                                <div class="sm:col-span-3"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salvar alterações</button></div>
                            </form>
                            <form method="POST" action="{{ route('subscriptions.toggle', ['subscription' => $subscription]) }}" class="mt-3">
                                @csrf
                                <button class="px-1 py-2 text-sm font-semibold text-red-600" @disabled($subscription->status === 'canceled')>{{ $subscription->status === 'canceled' ? 'Assinatura cancelada' : 'Cancelar assinatura agora' }}</button>
                            </form>
                        </div>
                    </details>
                    @endif
                </article>
            @empty
                <div class="portal-card col-span-full p-12 text-center"><h2 class="font-semibold">Nenhuma assinatura ainda</h2><p class="mt-2 text-sm text-zinc-500">Explore o catálogo e escolha o primeiro produto da sua empresa.</p></div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
