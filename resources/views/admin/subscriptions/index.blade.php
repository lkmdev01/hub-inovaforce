<x-layouts::app :title="__('Assinaturas administrativas')">
    @php($filters = ['' => 'Todas', 'active' => 'Ativas', 'trialing' => 'Teste', 'pending' => 'Pendentes', 'past_due' => 'Inadimplentes', 'canceled' => 'Canceladas'])
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div><p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">Administração</p><h1 class="text-3xl font-semibold tracking-tight">Assinaturas</h1><p class="mt-2 text-zinc-500">Acompanhe contratos, pagamentos e renovações dos clientes.</p></div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))<x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>@endif
        @endforeach

        <div class="flex flex-wrap gap-2">
            @foreach ($filters as $value => $label)
                <a href="{{ route('admin.subscriptions.index', $value ? ['status' => $value] : []) }}" wire:navigate class="rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $status === $value ? 'bg-violet-600 text-white' : 'bg-white text-zinc-600 ring-1 ring-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800' }}">{{ $label }}</a>
            @endforeach
        </div>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><h2 class="font-semibold">Contratos encontrados</h2><p class="mt-0.5 text-sm text-zinc-500">{{ $subscriptions->total() }} assinatura(s)</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60"><tr><th class="px-5 py-3 font-semibold">Cliente</th><th class="px-5 py-3 font-semibold">Produto e plano</th><th class="px-5 py-3 font-semibold">Valor</th><th class="px-5 py-3 font-semibold">Renovação</th><th class="px-5 py-3 font-semibold">Situação</th><th class="px-5 py-3 font-semibold">Acesso</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($subscriptions as $subscription)
                            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-900/40">
                                <td class="px-5 py-4"><a href="{{ route('admin.customers.show', $subscription->team) }}" wire:navigate class="font-semibold hover:text-violet-600">{{ $subscription->team->name }}</a><span class="block text-xs text-zinc-500">{{ $subscription->team->billingCustomer?->email }}</span></td>
                                <td class="px-5 py-4"><strong class="block">{{ $subscription->product->name }}</strong><span class="text-xs text-zinc-500">{{ $subscription->plan_name }} · {{ $subscription->seats }} acesso(s)</span></td>
                                <td class="px-5 py-4"><strong>R$ {{ number_format($subscription->amount, 2, ',', '.') }}</strong><span class="block text-xs text-zinc-500">/{{ $subscription->billing_cycle === 'yearly' ? 'ano' : 'mês' }}</span></td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $subscription->renews_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-5 py-4"><x-portal-status :status="$subscription->status" /></td>
                                <td class="px-5 py-4"><x-portal-status :status="$subscription->access_status" /></td>
                                <td class="px-5 py-4 text-right">
                                    @if (in_array($subscription->status, ['active', 'trialing', 'pending', 'past_due']))
                                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}" onsubmit="return confirm('Cancelar esta assinatura?')">@csrf<button class="text-sm font-semibold text-red-600 hover:text-red-500">Cancelar</button></form>
                                    @else
                                        <span class="text-xs text-zinc-400">Sem ações</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-zinc-500">Nenhuma assinatura encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($subscriptions->hasPages())<div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">{{ $subscriptions->links() }}</div>@endif
        </section>
    </div>
</x-layouts::app>
