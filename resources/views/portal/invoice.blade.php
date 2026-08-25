<x-layouts::app :title="$invoice->number">
    <div class="mx-auto w-full max-w-4xl">
        <div class="mb-5 flex items-center justify-between print:hidden">
            <a href="{{ route('invoices.index') }}" wire:navigate class="text-sm font-semibold text-zinc-500 hover:text-zinc-900">← Voltar para faturas</a>
            <div class="flex items-center gap-3">
                @if ($invoice->payment_url && in_array($invoice->status, ['open', 'overdue'], true))
                    <a href="{{ $invoice->payment_url }}" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Pagar no Asaas</a>
                @endif
                @if ($invoice->receipt_url)
                    <a href="{{ $invoice->receipt_url }}" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Comprovante</a>
                @endif
                <button onclick="window.print()" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Imprimir / salvar PDF</button>
            </div>
        </div>

        <article class="portal-card p-8 sm:p-12">
            <header class="flex flex-col justify-between gap-6 border-b border-zinc-200 pb-8 sm:flex-row dark:border-zinc-800">
                <div><p class="text-xl font-bold tracking-tight text-violet-600">INOVAFORCE</p><p class="mt-2 text-sm text-zinc-500">Tecnologia que move negócios.</p></div>
                <div class="sm:text-right"><p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Fatura</p><h1 class="mt-1 text-2xl font-semibold">{{ $invoice->number }}</h1><div class="mt-2"><x-portal-status :status="$invoice->status" /></div></div>
            </header>

            <div class="grid gap-8 py-8 sm:grid-cols-2">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Faturado para</p><p class="mt-2 font-semibold">{{ $current_team->name }}</p><p class="mt-1 text-sm text-zinc-500">{{ auth()->user()->email }}</p></div>
                <div class="grid grid-cols-2 gap-4 sm:text-right"><div><p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Emissão</p><p class="mt-2 text-sm font-medium">{{ $invoice->issued_at->format('d/m/Y') }}</p></div><div><p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Vencimento</p><p class="mt-2 text-sm font-medium">{{ $invoice->due_at->format('d/m/Y') }}</p></div></div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-sm"><thead class="bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500 dark:bg-zinc-800"><tr><th class="px-5 py-3">Descrição</th><th class="px-5 py-3 text-right">Valor</th></tr></thead><tbody><tr><td class="px-5 py-5"><p class="font-semibold">{{ $invoice->subscription?->product?->name ?? 'Serviços Inovaforce' }}</p><p class="mt-1 text-zinc-500">Plano {{ $invoice->subscription?->plan_name ?? 'personalizado' }} · cobrança {{ mb_strtolower(App\Models\ProductPlan::CYCLES[$invoice->subscription?->billing_cycle] ?? ($invoice->subscription?->billing_cycle ?? 'personalizada')) }}</p></td><td class="whitespace-nowrap px-5 py-5 text-right font-semibold">R$ {{ number_format($invoice->subtotal, 2, ',', '.') }}</td></tr></tbody></table>
            </div>

            <div class="mt-6 flex justify-end"><div class="w-full max-w-xs space-y-3"><div class="flex justify-between text-sm text-zinc-500"><span>Subtotal</span><span>R$ {{ number_format($invoice->subtotal, 2, ',', '.') }}</span></div><div class="flex justify-between border-t border-zinc-200 pt-3 text-lg font-semibold dark:border-zinc-800"><span>Total</span><span>R$ {{ number_format($invoice->total, 2, ',', '.') }}</span></div></div></div>

            @if ($invoice->fiscalDocuments->isNotEmpty())
                <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-800"><h2 class="font-semibold">Nota fiscal</h2><div class="mt-3 grid gap-3">@foreach($invoice->fiscalDocuments as $document)<div class="flex flex-wrap items-center gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900"><div class="min-w-0 flex-1"><strong class="block text-sm">NFS-e {{ $document->number ?: 'em processamento' }}</strong><span class="text-xs text-zinc-500">Código de validação: {{ $document->validation_code ?: 'aguardando' }}</span></div><x-portal-status :status="$document->status" />@if($document->pdf_url)<a href="{{ $document->pdf_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-violet-600">Baixar PDF</a>@endif @if($document->xml_url)<a href="{{ $document->xml_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-violet-600">Baixar XML</a>@endif</div>@endforeach</div></div>
            @endif

            @if ($invoice->financialEvents->isNotEmpty())
                <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-800"><h2 class="font-semibold">Histórico</h2><div class="mt-3 space-y-3">@foreach($invoice->financialEvents->sortByDesc('occurred_at') as $event)<div class="flex items-start gap-3"><span class="mt-1.5 size-2 rounded-full bg-violet-500"></span><div><strong class="block text-sm">{{ $event->title }}</strong><span class="text-xs text-zinc-500">{{ $event->occurred_at->format('d/m/Y H:i') }} · {{ $event->description }}</span></div></div>@endforeach</div></div>
            @endif
            <p class="mt-12 text-center text-xs text-zinc-400">Em caso de dúvidas, entre em contato com financeiro@inovaforce.com.br.</p>
        </article>
    </div>
</x-layouts::app>
