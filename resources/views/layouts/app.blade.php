<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if ($clientPreviewTeam = request()->attributes->get('clientPreviewTeam'))
            <div class="sticky top-3 z-40 mx-auto mb-6 flex w-full max-w-7xl flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50/95 px-4 py-3 shadow-lg shadow-amber-950/5 backdrop-blur sm:flex-row sm:items-center dark:border-amber-700 dark:bg-amber-950/95">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-200 text-amber-800 dark:bg-amber-800 dark:text-amber-100">
                    <flux:icon.eye class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-amber-950 dark:text-amber-100">Visualizando como cliente: {{ $clientPreviewTeam->name }}</p>
                    <p class="text-xs text-amber-800 dark:text-amber-300">Modo somente leitura · encerra automaticamente após {{ App\Support\ClientPreview::DURATION_MINUTES }} minutos</p>
                </div>
                <form method="POST" action="{{ route('admin.customers.preview.stop') }}">
                    @csrf
                    <button class="inline-flex h-9 w-full items-center justify-center rounded-xl bg-amber-900 px-4 text-sm font-semibold text-white hover:bg-amber-800 sm:w-auto dark:bg-amber-200 dark:text-amber-950 dark:hover:bg-amber-100">Encerrar visualização</button>
                </form>
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
