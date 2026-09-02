<x-layouts::app :title="__('Faturas')">
    @php
        $isClientPreview = request()->attributes->has('clientPreviewTeam');
    @endphp
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div>
            <p class="mb-1 text-sm font-medium text-violet-600">Financeiro</p>
            <h1 class="text-3xl font-semibold tracking-tight">Faturas</h1>
            <p class="mt-2 text-zinc-500">Acompanhe cobranças, vencimentos e comprovantes.</p>
        </div>

        <div class="portal-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900">
                        <tr><th class="px-5 py-3 font-semibold">Fatura</th><th class="px-5 py-3 font-semibold">Produto</th><th class="px-5 py-3 font-semibold">Emissão</th><th class="px-5 py-3 font-semibold">Vencimento</th><th class="px-5 py-3 font-semibold">Valor</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($invoices as $invoice)
                            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $invoice->number }}</td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $invoice->subscription?->product?->name ?? 'Serviços Inovaforce' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-zinc-500">{{ $invoice->issued_at->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-zinc-500">{{ $invoice->due_at->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold">R$ {{ number_format($invoice->total, 2, ',', '.') }}</td>
                                <td class="px-5 py-4"><x-portal-status :status="$invoice->status" /></td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @if (! $isClientPreview && $invoice->payment_url && in_array($invoice->status, ['open', 'overdue'], true))
                                            <a href="{{ $invoice->payment_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-emerald-600 hover:text-emerald-500">Pagar</a>
                                        @endif
                                        <a href="{{ route('invoices.show', ['invoice' => $invoice]) }}" class="font-semibold text-violet-600 hover:text-violet-500">Ver fatura</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-zinc-500">Nenhuma fatura emitida.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
