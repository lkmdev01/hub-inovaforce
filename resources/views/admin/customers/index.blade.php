<x-layouts::app :title="__('Clientes')">
    @php($groupColorClasses = ['violet' => 'bg-violet-500', 'blue' => 'bg-blue-500', 'emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'zinc' => 'bg-zinc-500'])
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-1 text-sm font-medium text-violet-600 dark:text-violet-400">Administração</p>
                <h1 class="text-3xl font-semibold tracking-tight">Clientes</h1>
                <p class="mt-2 text-zinc-500">Cadastre empresas e consulte seus acessos e assinaturas.</p>
            </div>
            <a href="#novo-cliente" class="inline-flex h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-500">Novo cliente</a>
        </div>

        @foreach (['success', 'warning', 'error'] as $message)
            @if (session($message))
                <x-portal-alert :type="$message">{{ session($message) }}</x-portal-alert>
            @endif
        @endforeach

        @if ($errors->any())
            <x-portal-alert type="error">Revise os campos destacados e tente novamente.</x-portal-alert>
        @endif

        <section class="portal-card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <div><h2 class="font-semibold">Empresas cadastradas</h2><p class="mt-0.5 text-sm text-zinc-500">{{ $customers->total() }} cliente(s) encontrado(s)</p></div>
                <form method="GET" action="{{ route('admin.customers.index') }}" class="flex w-full flex-col gap-2 sm:max-w-2xl sm:flex-row">
                    <input name="search" value="{{ $search }}" placeholder="Nome, e-mail ou documento" class="h-10 min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-3 text-sm outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />
                    <select name="group" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-violet-500 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Todos os grupos</option>
                        @foreach ($groups as $group)<option value="{{ $group->id }}" @selected($groupId === $group->id)>{{ $group->name }}</option>@endforeach
                    </select>
                    <button class="rounded-xl border border-zinc-300 px-4 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Buscar</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60">
                        <tr><th class="px-5 py-3 font-semibold">Cliente</th><th class="px-5 py-3 font-semibold">Grupo</th><th class="px-5 py-3 font-semibold">Documento</th><th class="px-5 py-3 font-semibold">Acessos</th><th class="px-5 py-3 font-semibold">Assinaturas</th><th class="px-5 py-3 font-semibold">Cobrança</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($customers as $customer)
                            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-900/40">
                                <td class="px-5 py-4"><strong class="block">{{ $customer->name }}</strong><span class="text-xs text-zinc-500">{{ $customer->email }}</span></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $customer->group?->name ?? 'Sem grupo' }}</span></td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $customer->tax_id ?: 'Não informado' }}</td>
                                <td class="px-5 py-4">{{ $customer->team->members->count() }}</td>
                                <td class="px-5 py-4">{{ $customer->team->subscriptions_count }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $customer->synced_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' }}">{{ $customer->synced_at ? 'Sincronizado' : 'Pendente' }}</span></td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('admin.customers.show', $customer->team) }}" wire:navigate class="font-semibold text-violet-600 hover:text-violet-500">Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-zinc-500">Nenhum cliente encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">{{ $customers->links() }}</div>
            @endif
        </section>

        <section id="novo-cliente" class="portal-card scroll-mt-6 p-5 sm:p-6">
            <div class="mb-6"><h2 class="text-lg font-semibold">Cadastrar novo cliente</h2><p class="mt-1 text-sm text-zinc-500">Cria a empresa, o primeiro usuário e o perfil de cobrança.</p></div>
            <form method="POST" action="{{ route('admin.customers.store') }}" class="grid gap-5 md:grid-cols-2">
                @csrf
                <label class="grid gap-1.5 text-sm font-medium">Nome da empresa<input name="company_name" value="{{ old('company_name') }}" required class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('company_name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">Responsável<input name="contact_name" value="{{ old('contact_name') }}" required class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('contact_name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">E-mail de acesso<input type="email" name="email" value="{{ old('email') }}" required class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('email')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">Senha inicial<input type="password" name="password" required minlength="8" autocomplete="new-password" class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('password')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">CPF ou CNPJ<input name="tax_id" value="{{ old('tax_id') }}" required placeholder="Somente números" class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('tax_id')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">Celular<input name="cellphone" value="{{ old('cellphone') }}" required placeholder="(11) 99999-9999" class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('cellphone')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">CEP <span class="font-normal text-zinc-400">(opcional)</span><input name="zip_code" value="{{ old('zip_code') }}" class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900" />@error('zip_code')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium">Grupo <span class="font-normal text-zinc-400">(opcional)</span><select name="customer_group_id" class="h-11 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Sem grupo</option>@foreach ($groups->where('active', true) as $group)<option value="{{ $group->id }}" @selected((int) old('customer_group_id') === $group->id)>{{ $group->name }}</option>@endforeach</select></label>
                <div class="flex items-end md:justify-end"><button class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-500 md:w-auto">Criar cliente e acesso</button></div>
            </form>
        </section>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-800"><h2 class="font-semibold">Grupos de clientes</h2><p class="mt-1 text-sm text-zinc-500">Organize a base e sincronize o mesmo agrupamento com o Asaas.</p></div>
            <div class="grid gap-6 p-5 lg:grid-cols-[1fr_1.2fr]">
                <form method="POST" action="{{ route('admin.customer-groups.store') }}" class="grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    @csrf
                    <label class="grid gap-1.5 text-sm font-medium">Nome do grupo<input name="name" value="{{ old('name') }}" required placeholder="Ex.: Enterprise ou Parceiros" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal outline-none focus:border-violet-500 dark:border-zinc-700 dark:bg-zinc-900" /></label>
                    <label class="grid gap-1.5 text-sm font-medium">Descrição<textarea name="description" rows="2" class="rounded-xl border border-zinc-300 bg-white px-3 py-2 font-normal outline-none focus:border-violet-500 dark:border-zinc-700 dark:bg-zinc-900">{{ old('description') }}</textarea></label>
                    <label class="grid gap-1.5 text-sm font-medium">Cor<select name="color" class="h-10 rounded-xl border border-zinc-300 bg-white px-3 font-normal dark:border-zinc-700 dark:bg-zinc-900"><option value="violet">Violeta</option><option value="blue">Azul</option><option value="emerald">Verde</option><option value="amber">Amarelo</option><option value="red">Vermelho</option><option value="zinc">Cinza</option></select></label>
                    <button class="h-10 rounded-xl bg-zinc-900 px-4 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">Criar grupo</button>
                </form>
                <div class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @forelse ($groups as $group)
                        <div class="flex items-center gap-4 p-4"><span class="size-3 rounded-full {{ $groupColorClasses[$group->color] ?? 'bg-violet-500' }}"></span><div class="min-w-0 flex-1"><strong class="block text-sm">{{ $group->name }}</strong><span class="text-xs text-zinc-500">{{ $group->customers_count }} cliente(s) · {{ $group->description ?: 'Sem descrição' }}</span></div><form method="POST" action="{{ route('admin.customer-groups.destroy', $group) }}" onsubmit="return confirm('Excluir este grupo?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Excluir</button></form></div>
                    @empty
                        <div class="p-8 text-center text-sm text-zinc-500">Nenhum grupo criado.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
