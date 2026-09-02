<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        @php
            $clientPreviewTeam = request()->attributes->get('clientPreviewTeam');
        @endphp
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ auth()->user()->is_admin && ! $clientPreviewTeam ? route('admin.dashboard') : route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if (! auth()->user()->is_admin)
                <livewire:team-switcher />
            @endif

            <flux:sidebar.nav>
                @if (auth()->user()->is_admin && ! $clientPreviewTeam)
                    <flux:sidebar.group :heading="__('Administração')" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Visão geral') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.customers.index')" :current="request()->routeIs('admin.customers.*')" wire:navigate>{{ __('Clientes') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.subscriptions.index')" :current="request()->routeIs('admin.subscriptions.*')" wire:navigate>{{ __('Assinaturas') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-bag" :href="route('admin.products.index')" :current="request()->routeIs('admin.products.*') || request()->routeIs('admin.plans.*')" wire:navigate>{{ __('Produtos e planos') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="bolt" :href="route('admin.automations.index')" :current="request()->routeIs('admin.automations.*')" wire:navigate>{{ __('Automações') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="$clientPreviewTeam ? __('Portal simulado') : __('Workspace')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Visão geral') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('subscriptions.index')" :current="request()->routeIs('subscriptions.*')" wire:navigate>{{ __('Assinaturas') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('invoices.index')" :current="request()->routeIs('invoices.*')" wire:navigate>{{ __('Faturas') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-bag" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>{{ __('Produtos') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="building-office" :href="route('customer.show')" :current="request()->routeIs('customer.*')" wire:navigate>{{ __('Dados do cliente') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="question-mark-circle" href="mailto:suporte@inovaforce.com.br">{{ __('Central de ajuda') }}</flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" :showTeam="! auth()->user()->is_admin" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @if (! auth()->user()->is_admin)
            <livewire:create-team-modal />
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
