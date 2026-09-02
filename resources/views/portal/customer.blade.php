<x-layouts::app :title="__('Dados do cliente')">
    @php
        $isClientPreview = request()->attributes->has('clientPreviewTeam');
    @endphp
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-7">
        <div>
            <p class="mb-1 text-sm font-medium text-violet-600">Cadastro</p>
            <h1 class="text-3xl font-semibold tracking-tight">Dados do cliente</h1>
            <p class="mt-2 text-zinc-500">Essas informações identificam sua empresa e preenchem automaticamente os checkouts.</p>
        </div>

        @foreach (['success', 'warning', 'error'] as $type)
            @if (session($type))<x-portal-alert :type="$type">{{ session($type) }}</x-portal-alert>@endif
        @endforeach

        <div class="portal-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                <div><h2 class="font-semibold">Perfil de cobrança</h2><p class="mt-1 text-sm text-zinc-500">CPF ou CNPJ é usado para evitar cadastros duplicados.</p></div>
                @if ($customer->external_customer_id)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Sincronizado</span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600"><span class="size-1.5 rounded-full bg-zinc-400"></span>Local</span>
                @endif
            </div>

            <form method="POST" action="{{ route('customer.update') }}" class="grid gap-5 p-6 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <fieldset class="contents" @disabled($isClientPreview)>
                <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Nome ou razão social</span><input name="name" value="{{ old('name', $customer->name) }}" required class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">E-mail financeiro</span><input name="email" type="email" value="{{ old('email', $customer->email) }}" required class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('email')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">CPF ou CNPJ</span><input name="tax_id" value="{{ old('tax_id', $customer->tax_id) }}" placeholder="00.000.000/0001-00" required class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('tax_id')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Celular</span><input name="cellphone" value="{{ old('cellphone', $customer->cellphone) }}" placeholder="(11) 99999-9999" required class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('cellphone')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">CEP</span><input name="zip_code" value="{{ old('zip_code', $customer->zip_code) }}" placeholder="00000-000" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('zip_code')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Endereço</span><input name="address" value="{{ old('address', $customer->address) }}" placeholder="Rua ou avenida" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('address')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Número</span><input name="address_number" value="{{ old('address_number', $customer->address_number) }}" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('address_number')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Complemento</span><input name="complement" value="{{ old('complement', $customer->complement) }}" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('complement')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Bairro</span><input name="province" value="{{ old('province', $customer->province) }}" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('province')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Inscrição municipal</span><input name="municipal_inscription" value="{{ old('municipal_inscription', $customer->municipal_inscription) }}" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('municipal_inscription')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Inscrição estadual</span><input name="state_inscription" value="{{ old('state_inscription', $customer->state_inscription) }}" class="rounded-xl border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">@error('state_inscription')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                </fieldset>
                <div class="flex items-center justify-between gap-4 border-t border-zinc-100 pt-5 sm:col-span-2 dark:border-zinc-800">
                    <p class="text-xs text-zinc-500">{{ $billingProvider->configured() ? 'Os dados serão sincronizados com o '.$billingProvider->label().'.' : 'Integração em modo local até configurar a chave.' }}</p>
                    @if ($isClientPreview)
                        <span class="rounded-xl bg-zinc-100 px-4 py-2.5 text-sm font-semibold text-zinc-500 dark:bg-zinc-800">Somente leitura</span>
                    @else
                        <button class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">Salvar cliente</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
