<x-layouts::app :title="__('Produtos')">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div>
            <p class="mb-1 text-sm font-medium text-violet-600">Ecossistema Inovaforce</p>
            <h1 class="text-3xl font-semibold tracking-tight">Produtos</h1>
            <p class="mt-2 max-w-2xl text-zinc-500">Soluções que trabalham juntas para organizar e acelerar sua operação.</p>
        </div>

        @foreach (['success', 'warning', 'error'] as $type)
            @if (session($type))<x-portal-alert :type="$type">{{ session($type) }}</x-portal-alert>@endif
        @endforeach

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $product)
                <article class="portal-card flex flex-col overflow-hidden p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-base font-bold text-white">{{ str($product->name)->substr(0, 2)->upper() }}</div>
                        @if ($subscribedProductIds->contains($product->id))<x-portal-status status="active" />@endif
                    </div>
                    <h2 class="mt-5 text-xl font-semibold">{{ $product->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">{{ $product->description }}</p>
                    <ul class="mt-5 flex-1 space-y-3">
                        @foreach ($product->features ?? [] as $feature)
                            <li class="flex items-start gap-2 text-sm"><flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-emerald-500" /><span>{{ $feature }}</span></li>
                        @endforeach
                    </ul>
                    <div class="mt-6 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        @if ($subscribedProductIds->contains($product->id))
                            <a href="{{ route('subscriptions.index') }}" wire:navigate class="flex h-10 items-center justify-center rounded-xl border border-zinc-200 text-sm font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Gerenciar assinatura</a>
                        @elseif ($product->plans->isNotEmpty())
                            <form method="POST" action="{{ route('subscriptions.store', ['plan' => $product->plans->first()]) }}" class="grid gap-3">
                                @csrf
                                <label class="grid gap-1.5 text-xs font-semibold text-zinc-500">Escolha o plano
                                    <select name="plan_preview" onchange="const option=this.options[this.selectedIndex]; this.form.action=option.dataset.action; const seats=this.form.elements.seats; seats.min=option.dataset.min; seats.max=option.dataset.max; if(Number(seats.value) < Number(seats.min)) seats.value=seats.min; if(Number(seats.value) > Number(seats.max)) seats.value=seats.max" class="rounded-xl border-zinc-300 bg-white text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                                        @foreach ($product->plans as $plan)
                                            <option data-action="{{ route('subscriptions.store', ['plan' => $plan]) }}" data-min="{{ $plan->minimum_seats }}" data-max="{{ $plan->maximum_seats ?? 500 }}">{{ $plan->name }} · R$ {{ number_format($plan->price, 2, ',', '.') }}{{ $plan->pricing_model === 'per_seat' ? '/licença' : '' }} · {{ App\Models\ProductPlan::CYCLES[$plan->billing_cycle] ?? $plan->billing_cycle }} · {{ App\Models\ProductPlan::BILLING_TYPES[$plan->billing_type] ?? $plan->billing_type }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="grid gap-1.5 text-xs font-semibold text-zinc-500">Quantidade de licenças
                                    <input name="seats" type="number" min="{{ $product->plans->first()->minimum_seats }}" max="{{ $product->plans->first()->maximum_seats ?? 500 }}" value="{{ $product->plans->first()->minimum_seats }}" required class="rounded-xl border-zinc-300 bg-white text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                                </label>
                                <button class="flex h-10 items-center justify-center rounded-xl bg-violet-600 text-sm font-semibold text-white hover:bg-violet-500" @disabled($product->plans->isEmpty())>Assinar agora</button>
                            </form>
                        @else
                            <p class="rounded-xl bg-zinc-100 px-3 py-2 text-center text-sm font-medium text-zinc-500">Planos em breve</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts::app>
