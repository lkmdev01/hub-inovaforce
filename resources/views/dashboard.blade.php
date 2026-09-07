<x-layouts::app :title="__('Comunidade Inovaforce')">
    @php
        $isClientPreview = request()->attributes->has('clientPreviewTeam');
        $activeSubscriptions = $subscriptions->whereIn('status', ['active', 'trialing']);
        $monthlyTotal = $activeSubscriptions->sum(fn ($subscription) => $subscription->monthlyEquivalentAmount());
        $accentClasses = [
            'violet' => 'from-brand-blue to-brand-cyan',
            'sky' => 'from-brand-cyan to-brand-aqua',
            'fuchsia' => 'from-brand-blue to-brand-aqua',
            'emerald' => 'from-emerald-500 to-teal-400',
            'amber' => 'from-amber-500 to-orange-500',
            'rose' => 'from-rose-500 to-pink-500',
        ];
    @endphp

    @unless ($isClientPreview)
        <livewire:pages::teams.pending-invitations-modal />
    @endunless

    <div id="comunidade" class="mx-auto flex w-full max-w-7xl scroll-mt-6 flex-col gap-7">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
        @endif

        <section class="relative isolate overflow-hidden rounded-[2rem] bg-brand-ink px-6 py-8 text-white shadow-2xl shadow-brand-blue/20 sm:px-9 sm:py-10 lg:px-12 lg:py-12">
            <div class="absolute -right-16 -top-24 -z-10 size-72 rounded-full bg-brand-blue/35 blur-3xl"></div>
            <div class="absolute -bottom-28 left-1/3 -z-10 size-72 rounded-full bg-brand-lime/15 blur-3xl"></div>
            <div class="absolute inset-0 -z-10 opacity-30 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.18)_1px,transparent_0)] [background-size:24px_24px]"></div>

            <div class="grid items-center gap-10 lg:grid-cols-[1.25fr_.75fr]">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-brand-aqua/30 bg-brand-aqua/10 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-brand-aqua backdrop-blur">
                        <span class="size-1.5 rounded-full bg-brand-lime shadow-[0_0_12px_rgba(182,255,59,.9)]"></span>
                        Comunidade Inovaforce
                    </span>
                    <p class="mt-6 text-sm font-medium text-brand-aqua">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                    <h1 class="mt-2 max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">{{ $isClientPreview ? 'Portal de '.$current_team->name : 'Olá, '.str(auth()->user()->name)->before(' ').'.' }}</h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-zinc-300 sm:text-lg">Produtos, pagamentos, suporte e novidades em um só lugar para sua empresa crescer com a Inovaforce.</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="#meus-produtos" class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-lime px-5 text-sm font-semibold text-brand-ink transition hover:bg-[#c6ff67]">Acessar meus produtos</a>
                        <a href="{{ route('products.index') }}" wire:navigate class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-aqua/30 bg-brand-aqua/5 px-5 text-sm font-semibold text-white backdrop-blur transition hover:bg-brand-aqua/10">Conhecer o ecossistema</a>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[.07] p-5 backdrop-blur-md">
                    <div class="flex items-center justify-between"><p class="text-sm font-semibold">Ecossistema Inovaforce</p><span class="text-xs text-zinc-400">{{ $catalogProducts->count() }} {{ $catalogProducts->count() === 1 ? 'solução' : 'soluções' }}</span></div>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        @forelse ($catalogProducts->take(4) as $product)
                            <div class="rounded-xl border border-white/10 bg-zinc-900/70 p-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br {{ $accentClasses[$product->accent] ?? $accentClasses['violet'] }} text-xs font-bold shadow-lg">{{ str($product->name)->substr(0, 2)->upper() }}</div>
                                <p class="mt-3 truncate text-sm font-semibold">{{ $product->name }}</p>
                                <p class="mt-0.5 text-[11px] text-zinc-400">{{ $activeSubscriptions->contains('product_id', $product->id) ? 'No seu plano' : 'Conhecer' }}</p>
                            </div>
                        @empty
                            <p class="col-span-2 py-8 text-center text-sm text-zinc-400">Novas soluções aparecerão aqui.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="portal-card p-5"><div class="flex items-start justify-between"><span class="portal-icon bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"><flux:icon.squares-2x2 class="size-5" /></span><span class="portal-kicker">Produtos</span></div><p class="mt-6 text-3xl font-semibold tracking-tight">{{ $activeSubscriptions->count() }}</p><p class="mt-1 text-sm text-zinc-500">soluções ativas no seu workspace</p></div>
            <div class="portal-card p-5"><div class="flex items-start justify-between"><span class="portal-icon bg-sky-100 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400"><flux:icon.credit-card class="size-5" /></span><span class="portal-kicker">Mensalidade</span></div><p class="mt-6 text-3xl font-semibold tracking-tight">R$ {{ number_format($monthlyTotal, 2, ',', '.') }}</p><p class="mt-1 text-sm text-zinc-500">custo mensal estimado</p></div>
            <div class="portal-card p-5"><div class="flex items-start justify-between"><span class="portal-icon bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><flux:icon.document-text class="size-5" /></span><span class="portal-kicker">Financeiro</span></div><p class="mt-6 text-3xl font-semibold tracking-tight">{{ $openInvoicesCount }}</p><p class="mt-1 text-sm text-zinc-500">faturas aguardando pagamento</p></div>
        </div>

        <section id="meus-produtos" class="portal-card scroll-mt-6 overflow-hidden">
            <div class="flex flex-col justify-between gap-3 border-b border-zinc-200 px-5 py-5 sm:flex-row sm:items-end dark:border-zinc-800">
                <div><p class="portal-kicker">Seu workspace</p><h2 class="mt-1 text-xl font-semibold">Meus produtos</h2><p class="mt-1 text-sm text-zinc-500">Acompanhe planos, acessos e renovações da {{ $current_team->name }}.</p></div>
                <a href="{{ route('subscriptions.index') }}" wire:navigate class="text-sm font-semibold text-violet-600 hover:text-violet-500">Gerenciar assinaturas</a>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($subscriptions->take(6) as $subscription)
                    <article class="group rounded-2xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-lg hover:shadow-violet-500/5 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-violet-800">
                        <div class="flex items-start justify-between gap-3"><div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br {{ $accentClasses[$subscription->product->accent] ?? $accentClasses['violet'] }} text-sm font-bold text-white shadow-sm">{{ str($subscription->product->name)->substr(0, 2)->upper() }}</div><x-portal-status :status="$subscription->status" /></div>
                        <h3 class="mt-5 font-semibold">{{ $subscription->product->name }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">Plano {{ $subscription->plan_name }} · {{ $subscription->seats }} {{ $subscription->seats === 1 ? 'acesso' : 'acessos' }}</p>
                        <div class="mt-5 flex items-end justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800"><div><p class="text-xs text-zinc-500">Investimento</p><p class="font-semibold">R$ {{ number_format($subscription->amount, 2, ',', '.') }}</p></div><a href="{{ route('subscriptions.index') }}" wire:navigate class="text-sm font-semibold text-violet-600 group-hover:text-violet-500">Ver detalhes</a></div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center md:col-span-2 xl:col-span-3 dark:border-zinc-700"><p class="font-semibold">Seu workspace está pronto para começar</p><p class="mt-1 text-sm text-zinc-500">Conheça as soluções da Inovaforce e escolha a ideal para sua empresa.</p><a href="{{ route('products.index') }}" wire:navigate class="mt-4 inline-flex h-10 items-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Explorar produtos</a></div>
                @endforelse
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
            <section class="portal-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><div><p class="portal-kicker">Descubra</p><h2 class="mt-1 font-semibold">Mais do ecossistema</h2></div><a href="{{ route('products.index') }}" wire:navigate class="text-sm font-semibold text-violet-600">Ver catálogo</a></div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recommendedProducts as $product)
                        <a href="{{ route('products.index') }}#produto-{{ $product->id }}" wire:navigate class="flex items-center gap-4 p-5 transition hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $accentClasses[$product->accent] ?? $accentClasses['violet'] }} text-xs font-bold text-white">{{ str($product->name)->substr(0, 2)->upper() }}</div>
                            <div class="min-w-0 flex-1"><p class="font-semibold">{{ $product->name }}</p><p class="mt-1 line-clamp-1 text-sm text-zinc-500">{{ $product->description }}</p></div>
                            @if ($product->plans->isNotEmpty())<span class="hidden text-right text-xs text-zinc-500 sm:block">A partir de<br><strong class="text-sm text-zinc-900 dark:text-white">R$ {{ number_format((float) $product->plans->first()->price, 2, ',', '.') }}</strong></span>@endif
                            <flux:icon.chevron-right class="size-4 text-zinc-400" />
                        </a>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Você já conhece todas as soluções disponíveis.</div>
                    @endforelse
                </div>
            </section>

            <section class="portal-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800"><p class="portal-kicker">Comunidade</p><h2 class="mt-1 font-semibold">Sua central Inovaforce</h2></div>
                <div class="space-y-1 p-3">
                    <a href="{{ route('invoices.index') }}" wire:navigate class="portal-action"><span class="portal-icon bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10"><flux:icon.banknotes class="size-5" /></span><span class="flex-1"><strong class="block text-sm">Financeiro organizado</strong><span class="text-xs text-zinc-500">Faturas, notas e comprovantes</span></span><flux:icon.chevron-right class="size-4 text-zinc-400" /></a>
                    <a href="{{ route('products.index') }}" wire:navigate class="portal-action"><span class="portal-icon bg-violet-100 text-violet-600 dark:bg-violet-500/10"><flux:icon.shopping-bag class="size-5" /></span><span class="flex-1"><strong class="block text-sm">Produtos e lançamentos</strong><span class="text-xs text-zinc-500">Novas soluções para sua empresa</span></span><flux:icon.chevron-right class="size-4 text-zinc-400" /></a>
                    <a href="mailto:suporte@inovaforce.com.br" class="portal-action"><span class="portal-icon bg-sky-100 text-sky-600 dark:bg-sky-500/10"><flux:icon.question-mark-circle class="size-5" /></span><span class="flex-1"><strong class="block text-sm">Fale com a Inovaforce</strong><span class="text-xs text-zinc-500">Suporte e orientação quando precisar</span></span><flux:icon.chevron-right class="size-4 text-zinc-400" /></a>
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
