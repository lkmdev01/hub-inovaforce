<x-layouts::app :title="__('Produtos e planos')">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div><p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">Administração</p><h1 class="text-3xl font-semibold tracking-tight">Produtos e planos</h1><p class="mt-2 text-zinc-500">Defina o que aparece no catálogo e os valores usados nos novos checkouts.</p></div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))<x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>@endif
        @endforeach
        @if ($errors->any())<x-portal-alert type="error">Revise os campos destacados e tente novamente.</x-portal-alert>@endif

        <x-portal-alert type="warning">Alterações de preço afetam novos checkouts. Assinaturas existentes só mudam quando o cliente ou um administrador troca o plano.</x-portal-alert>

        <div class="grid gap-6">
            @forelse ($products as $product)
                <section class="portal-card overflow-hidden">
                    <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="grid gap-4 lg:grid-cols-[1fr_2fr_180px_auto] lg:items-end">
                            @csrf @method('PUT')
                            <label class="grid gap-1.5 text-sm font-medium">Nome<input name="name" value="{{ old('name', $product->name) }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" /></label>
                            <label class="grid gap-1.5 text-sm font-medium">Descrição<input name="description" value="{{ old('description', $product->description) }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" /></label>
                            <label class="grid gap-1.5 text-sm font-medium">Disponibilidade<select name="status" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 dark:border-zinc-700 dark:bg-zinc-900"><option value="active" @selected($product->status === 'active')>Ativo no catálogo</option><option value="inactive" @selected($product->status === 'inactive')>Inativo</option></select></label>
                            <button class="h-10 rounded-xl bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">Salvar produto</button>
                            <details class="lg:col-span-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" @if($product->fiscal_enabled) open @endif>
                                <summary class="cursor-pointer font-semibold">Nota fiscal automática <span class="ml-2 text-xs font-normal text-zinc-500">{{ $product->fiscal_enabled ? 'Ativada' : 'Desativada' }}</span></summary>
                                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <label class="flex items-center gap-2 text-sm font-medium md:col-span-2 xl:col-span-4"><input type="checkbox" name="fiscal_enabled" value="1" @checked(old('fiscal_enabled', $product->fiscal_enabled)) class="size-4 rounded border-zinc-300 text-violet-600" /> Emitir NFS-e automaticamente para este produto</label>
                                    <label class="grid gap-1.5 text-sm font-medium">ID do serviço municipal<input name="municipal_service_id" value="{{ old('municipal_service_id', $product->municipal_service_id) }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <label class="grid gap-1.5 text-sm font-medium">Código municipal<input name="municipal_service_code" value="{{ old('municipal_service_code', $product->municipal_service_code) }}" placeholder="Ex.: 1.01" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <label class="grid gap-1.5 text-sm font-medium md:col-span-2">Nome do serviço<input name="municipal_service_name" value="{{ old('municipal_service_name', $product->municipal_service_name) }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <label class="grid gap-1.5 text-sm font-medium md:col-span-2">Descrição na nota<textarea name="fiscal_service_description" rows="3" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ old('fiscal_service_description', $product->fiscal_service_description) }}</textarea></label>
                                    <label class="grid gap-1.5 text-sm font-medium md:col-span-2">Observações<textarea name="fiscal_observations" rows="3" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ old('fiscal_observations', $product->fiscal_observations) }}</textarea></label>
                                    <label class="grid gap-1.5 text-sm font-medium">Momento da emissão<select name="fiscal_effective_period" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900"><option value="ON_PAYMENT_CONFIRMATION" @selected($product->fiscal_effective_period === 'ON_PAYMENT_CONFIRMATION')>Após confirmação</option><option value="ON_PAYMENT_DUE_DATE" @selected($product->fiscal_effective_period === 'ON_PAYMENT_DUE_DATE')>No vencimento</option><option value="ON_DUE_DATE_MONTH" @selected($product->fiscal_effective_period === 'ON_DUE_DATE_MONTH')>No mês do vencimento</option><option value="ON_NEXT_MONTH" @selected($product->fiscal_effective_period === 'ON_NEXT_MONTH')>No mês seguinte</option></select></label>
                                    <label class="grid gap-1.5 text-sm font-medium">Deduções (R$)<input type="number" step="0.01" min="0" name="fiscal_deductions" value="{{ old('fiscal_deductions', $product->fiscal_deductions) }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    @php($taxes = $product->fiscal_taxes ?? [])
                                    <label class="grid gap-1.5 text-sm font-medium">ISS (%)<input type="number" step="0.01" min="0" max="100" name="tax_iss" value="{{ old('tax_iss', $taxes['iss'] ?? 0) }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <label class="flex items-end gap-2 pb-2 text-sm font-medium"><input type="checkbox" name="tax_retain_iss" value="1" @checked(old('tax_retain_iss', $taxes['retainIss'] ?? false)) class="size-4 rounded border-zinc-300 text-violet-600" /> Reter ISS</label>
                                    @foreach (['cofins' => 'COFINS', 'csll' => 'CSLL', 'inss' => 'INSS', 'ir' => 'IR', 'pis' => 'PIS'] as $taxKey => $taxLabel)
                                        <label class="grid gap-1.5 text-sm font-medium">{{ $taxLabel }} (%)<input type="number" step="0.01" min="0" max="100" name="tax_{{ $taxKey }}" value="{{ old('tax_'.$taxKey, $taxes[$taxKey] ?? 0) }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    @endforeach
                                    <p class="text-xs text-amber-700 md:col-span-2 xl:col-span-4 dark:text-amber-400">Use somente os códigos e alíquotas confirmados pela contabilidade. A conta Asaas também precisa estar habilitada para NFS-e.</p>
                                </div>
                            </details>
                            <details class="lg:col-span-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" @if($product->provisioning_webhook_url) open @endif>
                                <summary class="cursor-pointer font-semibold">Integração de acesso do software <span class="ml-2 text-xs font-normal text-zinc-500">{{ $product->provisioning_webhook_url ? 'Conectada' : 'Opcional' }}</span></summary>
                                <div class="mt-4 grid gap-4 md:grid-cols-[2fr_1fr]">
                                    <label class="grid gap-1.5 text-sm font-medium">URL para liberar ou bloquear acessos<input type="url" name="provisioning_webhook_url" value="{{ old('provisioning_webhook_url', $product->provisioning_webhook_url) }}" placeholder="https://seu-software.com/webhooks/inovaforce" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <label class="grid gap-1.5 text-sm font-medium">Segredo de assinatura<input type="password" name="provisioning_webhook_secret" placeholder="{{ $product->provisioning_webhook_secret ? 'Preencha apenas para trocar' : 'Mínimo de 32 caracteres' }}" autocomplete="new-password" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <p class="text-xs text-zinc-500 md:col-span-2">O Hub enviará um evento assinado no cabeçalho <code>X-Hub-Signature</code> sempre que o acesso for ativado, suspenso ou revogado.</p>
                                </div>
                            </details>
                        </form>
                        <p class="mt-3 text-xs text-zinc-500">{{ $product->subscriptions_count }} assinatura(s) vinculada(s) · identificador: {{ $product->slug }}</p>
                    </div>
                    <div class="grid gap-4 bg-zinc-50/60 p-5 md:grid-cols-2 xl:grid-cols-3 dark:bg-zinc-900/30">
                        @forelse ($product->plans as $plan)
                            <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                @csrf @method('PUT')
                                <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $plan->name }}</h3><p class="text-xs text-zinc-500">Cobrança {{ $plan->billing_cycle === 'yearly' ? 'anual' : 'mensal' }}</p></div><span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $plan->subscriptions()->count() }} cliente(s)</span></div>
                                <div class="grid gap-4">
                                    <label class="grid gap-1.5 text-sm font-medium">Preço (R$)<input type="number" step="0.01" min="1" name="price" value="{{ old('price', $plan->price) }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    <button class="h-10 rounded-xl border border-zinc-300 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Salvar plano</button>
                                </div>
                            </form>
                        @empty
                            <p class="text-sm text-zinc-500">Nenhum plano cadastrado para este produto.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="portal-card p-10 text-center text-zinc-500">Nenhum produto cadastrado.</div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
