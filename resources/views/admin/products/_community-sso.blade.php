<section id="sso-comunidade-{{ $product->id }}" class="scroll-mt-6 border-b border-zinc-200 p-5 dark:border-zinc-800">
    @php($ssoCredentials = session('community_sso_credentials'))
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div class="max-w-3xl">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">Entrada automática na comunidade</h3>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $product->community_sso_enabled ? $statusStyles['active'] : $statusStyles['archived'] }}">{{ $product->community_sso_enabled ? 'Ativa' : 'Desativada' }}</span>
            </div>
            <p class="mt-1 text-sm text-zinc-500">Permite que o dashboard deste produto abra o Hub sem pedir outra senha. A assinatura do cliente é conferida a cada entrada.</p>
            <div class="mt-4 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Endpoint do backend</p>
                <code class="mt-1 block break-all text-xs text-zinc-800 dark:text-zinc-200">POST {{ route('community.sso.launch', ['product' => $product->slug]) }}</code>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.products.community-sso.rotate', $product) }}" onsubmit="return confirm('{{ $product->community_sso_secret ? 'A credencial atual deixará de funcionar. Deseja gerar uma nova?' : 'Ativar a entrada automática para este produto?' }}')">
                @csrf
                <button class="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">{{ $product->community_sso_secret ? 'Trocar credencial' : 'Ativar integração' }}</button>
            </form>
            @if ($product->community_sso_enabled)
                <form method="POST" action="{{ route('admin.products.community-sso.disable', $product) }}" onsubmit="return confirm('Desativar a entrada automática deste produto?')">
                    @csrf
                    @method('DELETE')
                    <button class="h-10 rounded-xl border border-zinc-300 px-4 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Desativar</button>
                </form>
            @endif
        </div>
    </div>

    @if (data_get($ssoCredentials, 'product_id') === $product->id)
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-900/70 dark:bg-amber-950/30">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Copie esta credencial agora</p>
            <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">Ela é exibida uma única vez e deve ficar somente no backend do produto.</p>
            <input readonly value="{{ data_get($ssoCredentials, 'secret') }}" class="mt-3 h-10 w-full rounded-lg border border-amber-300 bg-white px-3 font-mono text-xs text-zinc-900 dark:border-amber-800 dark:bg-zinc-950 dark:text-white" onclick="this.select()" />
        </div>
    @endif
</section>
