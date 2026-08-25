<x-layouts::app :title="__('Produtos e planos')">
    @php
        $statusLabels = ['draft' => 'Rascunho', 'active' => 'Ativo', 'archived' => 'Arquivado'];
        $statusStyles = ['draft' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'archived' => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">Administração</p>
                <h1 class="text-3xl font-semibold tracking-tight">Produtos e planos</h1>
                <p class="mt-2 max-w-3xl text-zinc-500">Gerencie seu catálogo no Hub. Os dados são enviados ao Asaas automaticamente quando o cliente inicia uma contratação.</p>
            </div>
            <a href="#novo-produto" class="inline-flex h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Novo produto</a>
        </div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))<x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>@endif
        @endforeach
        @if ($errors->any())
            <x-portal-alert type="error"><strong>Revise os campos:</strong> {{ $errors->first() }}</x-portal-alert>
        @endif

        <section id="novo-produto" class="portal-card scroll-mt-6 overflow-hidden">
            <details class="group" @if($errors->any()) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                    <div><h2 class="font-semibold">Cadastrar novo produto</h2><p class="mt-1 text-sm text-zinc-500">O primeiro plano é criado junto para o produto já nascer pronto para o catálogo.</p></div>
                    <flux:icon.chevron-down class="size-5 transition group-open:rotate-180" />
                </summary>
                <form method="POST" action="{{ route('admin.products.store') }}" class="grid gap-5 border-t border-zinc-200 p-5 dark:border-zinc-800 lg:grid-cols-2">
                    @csrf
                    <div class="grid gap-4 rounded-2xl bg-zinc-50 p-5 dark:bg-zinc-900/50">
                        <div><h3 class="font-semibold">Produto</h3><p class="text-xs text-zinc-500">Informações exibidas no portal do cliente.</p></div>
                        <label class="grid gap-1.5 text-sm font-medium">Nome<input name="name" value="{{ old('name') }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                        <label class="grid gap-1.5 text-sm font-medium">Identificador opcional<input name="slug" value="{{ old('slug') }}" placeholder="Gerado automaticamente se ficar vazio" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                        <label class="grid gap-1.5 text-sm font-medium">Descrição<textarea name="description" rows="3" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-950">{{ old('description') }}</textarea></label>
                        <label class="grid gap-1.5 text-sm font-medium">Recursos <span class="font-normal text-zinc-500">(um por linha)</span><textarea name="features" rows="4" placeholder="Painel de indicadores&#10;Usuários ilimitados&#10;Suporte prioritário" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-950">{{ old('features') }}</textarea></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-medium">Publicação<select name="status" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950"><option value="draft" @selected(old('status', 'draft') === 'draft')>Rascunho</option><option value="active" @selected(old('status') === 'active')>Ativo no catálogo</option></select></label>
                            <label class="grid gap-1.5 text-sm font-medium">Cor<select name="accent" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950">@foreach (['violet' => 'Violeta', 'sky' => 'Azul', 'fuchsia' => 'Fúcsia', 'emerald' => 'Verde', 'amber' => 'Âmbar', 'rose' => 'Rosa'] as $value => $label)<option value="{{ $value }}" @selected(old('accent', 'violet') === $value)>{{ $label }}</option>@endforeach</select></label>
                        </div>
                    </div>

                    <div class="grid content-start gap-4 rounded-2xl bg-violet-50/60 p-5 dark:bg-violet-500/5">
                        <div><h3 class="font-semibold">Primeiro plano</h3><p class="text-xs text-zinc-500">Você poderá adicionar outros planos depois.</p></div>
                        <label class="grid gap-1.5 text-sm font-medium">Nome do plano<input name="plan_name" value="{{ old('plan_name', 'Essencial') }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-medium">Ciclo<select name="billing_cycle" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950">@foreach (App\Models\ProductPlan::CYCLES as $value => $label)<option value="{{ $value }}" @selected(old('billing_cycle', 'monthly') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="grid gap-1.5 text-sm font-medium">Pagamento<select name="billing_type" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950">@foreach (App\Models\ProductPlan::BILLING_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('billing_type', 'CREDIT_CARD') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="grid gap-1.5 text-sm font-medium">Preço (R$)<input name="price" type="number" step="0.01" min="1" value="{{ old('price') }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                            <label class="grid gap-1.5 text-sm font-medium">Cobrança<select name="pricing_model" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950">@foreach (App\Models\ProductPlan::PRICING_MODELS as $value => $label)<option value="{{ $value }}" @selected(old('pricing_model', 'flat') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="grid gap-1.5 text-sm font-medium">Mínimo de licenças<input name="minimum_seats" type="number" min="1" max="500" value="{{ old('minimum_seats', 1) }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                            <label class="grid gap-1.5 text-sm font-medium">Máximo de licenças<input name="maximum_seats" type="number" min="1" max="500" value="{{ old('maximum_seats') }}" placeholder="Sem limite até 500" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-950" /></label>
                        </div>
                        <button class="mt-2 h-11 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white hover:bg-violet-500">Criar produto e plano</button>
                    </div>
                </form>
            </details>
        </section>

        <x-portal-alert type="warning">Alterações de catálogo afetam novas contratações. Produtos e planos arquivados continuam preservados nas assinaturas existentes.</x-portal-alert>

        <div class="grid gap-6">
            @forelse ($products as $product)
                <section id="produto-{{ $product->id }}" class="portal-card scroll-mt-6 overflow-hidden">
                    <div class="flex flex-col justify-between gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-violet-600 font-bold text-white">{{ str($product->name)->substr(0, 2)->upper() }}</div>
                            <div><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold">{{ $product->name }}</h2><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusStyles[$product->status] ?? $statusStyles['draft'] }}">{{ $statusLabels[$product->status] ?? ucfirst($product->status) }}</span></div><p class="mt-0.5 text-xs text-zinc-500">{{ $product->subscriptions_count }} assinatura(s) · {{ $product->slug }}</p></div>
                        </div>
                        <span class="text-sm text-zinc-500">{{ $product->plans->where('status', 'active')->count() }} plano(s) disponível(is)</span>
                    </div>

                    <details class="group border-b border-zinc-200 dark:border-zinc-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-semibold hover:bg-zinc-50 dark:hover:bg-zinc-900/50">Editar produto, emissão fiscal e acesso <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
                        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="grid gap-5 border-t border-zinc-100 p-5 dark:border-zinc-800 lg:grid-cols-2">
                            @csrf @method('PUT')
                            <div class="grid content-start gap-4">
                                <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Nome<input name="name" value="{{ $product->name }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label><label class="grid gap-1.5 text-sm font-medium">Identificador<input name="slug" value="{{ $product->slug }}" required class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label></div>
                                <label class="grid gap-1.5 text-sm font-medium">Descrição<textarea name="description" rows="3" required class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ $product->description }}</textarea></label>
                                <label class="grid gap-1.5 text-sm font-medium">Recursos <span class="font-normal text-zinc-500">(um por linha)</span><textarea name="features" rows="4" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ implode(PHP_EOL, $product->features ?? []) }}</textarea></label>
                                <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Situação<select name="status" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected($product->status === $value)>{{ $label }}</option>@endforeach</select></label><label class="grid gap-1.5 text-sm font-medium">Cor<select name="accent" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (['violet' => 'Violeta', 'sky' => 'Azul', 'fuchsia' => 'Fúcsia', 'emerald' => 'Verde', 'amber' => 'Âmbar', 'rose' => 'Rosa'] as $value => $label)<option value="{{ $value }}" @selected($product->accent === $value)>{{ $label }}</option>@endforeach</select></label></div>
                            </div>

                            <div class="grid content-start gap-4">
                                <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" @if($product->fiscal_enabled) open @endif>
                                    <summary class="cursor-pointer font-semibold">Nota fiscal automática <span class="ml-2 text-xs font-normal text-zinc-500">{{ $product->fiscal_enabled ? 'Ativada' : 'Desativada' }}</span></summary>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <label class="flex items-center gap-2 text-sm font-medium sm:col-span-2"><input type="checkbox" name="fiscal_enabled" value="1" @checked($product->fiscal_enabled) class="size-4 rounded border-zinc-300 text-violet-600" /> Emitir NFS-e automaticamente</label>
                                        <label class="grid gap-1.5 text-sm font-medium">ID do serviço municipal<input name="municipal_service_id" value="{{ $product->municipal_service_id }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1.5 text-sm font-medium">Código municipal<input name="municipal_service_code" value="{{ $product->municipal_service_code }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Nome do serviço<input name="municipal_service_name" value="{{ $product->municipal_service_name }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Descrição da nota<textarea name="fiscal_service_description" rows="2" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ $product->fiscal_service_description }}</textarea></label>
                                        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Observações<textarea name="fiscal_observations" rows="2" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal dark:border-zinc-700 dark:bg-zinc-900">{{ $product->fiscal_observations }}</textarea></label>
                                        <label class="grid gap-1.5 text-sm font-medium">Emissão<select name="fiscal_effective_period" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900"><option value="ON_PAYMENT_CONFIRMATION" @selected($product->fiscal_effective_period === 'ON_PAYMENT_CONFIRMATION')>Após confirmação</option><option value="ON_PAYMENT_DUE_DATE" @selected($product->fiscal_effective_period === 'ON_PAYMENT_DUE_DATE')>No vencimento</option><option value="ON_DUE_DATE_MONTH" @selected($product->fiscal_effective_period === 'ON_DUE_DATE_MONTH')>No mês do vencimento</option><option value="ON_NEXT_MONTH" @selected($product->fiscal_effective_period === 'ON_NEXT_MONTH')>No mês seguinte</option></select></label>
                                        <label class="grid gap-1.5 text-sm font-medium">Deduções (R$)<input type="number" step="0.01" min="0" name="fiscal_deductions" value="{{ $product->fiscal_deductions }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        @php($taxes = $product->fiscal_taxes ?? [])
                                        @foreach (['iss' => 'ISS', 'cofins' => 'COFINS', 'csll' => 'CSLL', 'inss' => 'INSS', 'ir' => 'IR', 'pis' => 'PIS'] as $taxKey => $taxLabel)<label class="grid gap-1.5 text-sm font-medium">{{ $taxLabel }} (%)<input type="number" step="0.01" min="0" max="100" name="tax_{{ $taxKey }}" value="{{ $taxes[$taxKey] ?? 0 }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>@endforeach
                                        <label class="flex items-center gap-2 text-sm font-medium sm:col-span-2"><input type="checkbox" name="tax_retain_iss" value="1" @checked($taxes['retainIss'] ?? false) class="size-4 rounded border-zinc-300 text-violet-600" /> Reter ISS</label>
                                    </div>
                                </details>

                                <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" @if($product->provisioning_webhook_url) open @endif>
                                    <summary class="cursor-pointer font-semibold">Integração de acesso <span class="ml-2 text-xs font-normal text-zinc-500">{{ $product->provisioning_webhook_url ? 'Conectada' : 'Opcional' }}</span></summary>
                                    <div class="mt-4 grid gap-4"><label class="grid gap-1.5 text-sm font-medium">URL para liberar ou bloquear acessos<input type="url" name="provisioning_webhook_url" value="{{ $product->provisioning_webhook_url }}" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label><label class="grid gap-1.5 text-sm font-medium">Segredo de assinatura<input type="password" name="provisioning_webhook_secret" placeholder="{{ $product->provisioning_webhook_secret ? 'Preencha somente para trocar' : 'Mínimo de 32 caracteres' }}" autocomplete="new-password" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label><p class="text-xs text-zinc-500">O Hub assina os eventos no cabeçalho <code>X-Hub-Signature</code>.</p></div>
                                </details>
                            </div>
                            <div class="flex justify-end lg:col-span-2"><button class="h-10 rounded-xl bg-zinc-900 px-5 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900">Salvar produto</button></div>
                        </form>
                    </details>

                    <div class="grid gap-4 bg-zinc-50/60 p-5 dark:bg-zinc-900/30">
                        <div><h3 class="font-semibold">Planos</h3><p class="text-xs text-zinc-500">Planos inativos não aparecem para novas contratações.</p></div>
                        <div class="grid gap-4 xl:grid-cols-2">
                            @foreach ($product->plans as $plan)
                                <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                    @csrf @method('PUT')
                                    <div class="flex items-center justify-between"><h4 class="font-semibold">{{ $plan->name }}</h4><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $plan->status === 'active' ? $statusStyles['active'] : $statusStyles['archived'] }}">{{ $plan->status === 'active' ? 'Ativo' : 'Inativo' }}</span></div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="grid gap-1 text-xs font-semibold">Nome<input name="name" value="{{ $plan->name }}" required class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1 text-xs font-semibold">Situação<select name="status" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900"><option value="active" @selected($plan->status === 'active')>Ativo</option><option value="inactive" @selected($plan->status === 'inactive')>Inativo</option></select></label>
                                        <label class="grid gap-1 text-xs font-semibold">Ciclo<select name="billing_cycle" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::CYCLES as $value => $label)<option value="{{ $value }}" @selected($plan->billing_cycle === $value)>{{ $label }}</option>@endforeach</select></label>
                                        <label class="grid gap-1 text-xs font-semibold">Pagamento<select name="billing_type" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::BILLING_TYPES as $value => $label)<option value="{{ $value }}" @selected($plan->billing_type === $value)>{{ $label }}</option>@endforeach</select></label>
                                        <label class="grid gap-1 text-xs font-semibold">Preço (R$)<input type="number" step="0.01" min="1" name="price" value="{{ $plan->price }}" required class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1 text-xs font-semibold">Modelo<select name="pricing_model" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::PRICING_MODELS as $value => $label)<option value="{{ $value }}" @selected($plan->pricing_model === $value)>{{ $label }}</option>@endforeach</select></label>
                                        <label class="grid gap-1 text-xs font-semibold">Mínimo de licenças<input type="number" min="1" max="500" name="minimum_seats" value="{{ $plan->minimum_seats }}" required class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                        <label class="grid gap-1 text-xs font-semibold">Máximo de licenças<input type="number" min="1" max="500" name="maximum_seats" value="{{ $plan->maximum_seats }}" placeholder="Até 500" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    </div>
                                    <div class="flex items-center justify-between gap-3"><p class="text-xs text-zinc-500">{{ $plan->subscriptions()->count() }} assinatura(s)</p><button class="h-9 rounded-lg border border-zinc-300 px-4 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Salvar plano</button></div>
                                </form>
                            @endforeach
                        </div>

                        <details class="group rounded-2xl border border-dashed border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-950">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold"><span>Adicionar outro plano</span><flux:icon.plus class="size-4" /></summary>
                            <form method="POST" action="{{ route('admin.plans.store', $product) }}" class="grid gap-3 border-t border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-800">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Nome<input name="name" required placeholder="Ex.: Profissional" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                <label class="grid gap-1 text-xs font-semibold">Situação<select name="status" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900"><option value="active">Ativo</option><option value="inactive">Inativo</option></select></label>
                                <label class="grid gap-1 text-xs font-semibold">Ciclo<select name="billing_cycle" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::CYCLES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="grid gap-1 text-xs font-semibold">Pagamento<select name="billing_type" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::BILLING_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="grid gap-1 text-xs font-semibold">Preço (R$)<input type="number" step="0.01" min="1" name="price" required class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                <label class="grid gap-1 text-xs font-semibold">Modelo<select name="pricing_model" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900">@foreach (App\Models\ProductPlan::PRICING_MODELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="grid gap-1 text-xs font-semibold">Mínimo de licenças<input type="number" min="1" max="500" name="minimum_seats" value="1" required class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                <label class="grid gap-1 text-xs font-semibold">Máximo de licenças<input type="number" min="1" max="500" name="maximum_seats" placeholder="Até 500" class="h-9 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                <div class="flex justify-end sm:col-span-2 lg:col-span-4"><button class="h-9 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Adicionar plano</button></div>
                            </form>
                        </details>
                    </div>
                </section>
            @empty
                <div class="portal-card p-10 text-center text-zinc-500">Nenhum produto cadastrado.</div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
