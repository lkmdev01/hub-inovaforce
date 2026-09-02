<x-layouts::auth title="Aceite dos termos">
    <div class="space-y-6">
        <div><h1 class="text-2xl font-semibold">Antes de continuar</h1><p class="mt-2 text-sm text-zinc-500">Olá, {{ $user->name }}. Precisamos registrar seu aceite para liberar o portal.</p></div>
        @if (session('warning'))<x-portal-alert type="warning">{{ session('warning') }}</x-portal-alert>@endif
        <form method="POST" action="{{ route('legal.accept.store') }}" class="space-y-5">
            @csrf
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-zinc-300 text-violet-600"><span>Li e aceito os <a href="{{ route('legal.terms') }}" target="_blank" class="font-semibold text-violet-600">Termos de Uso</a> e a <a href="{{ route('legal.privacy') }}" target="_blank" class="font-semibold text-violet-600">Política de Privacidade</a>.</span></label>
            @error('terms')<p class="text-sm text-red-600">O aceite é necessário.</p>@enderror
            <button class="h-11 w-full rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Aceitar e entrar no Hub</button>
        </form>
    </div>
</x-layouts::auth>
