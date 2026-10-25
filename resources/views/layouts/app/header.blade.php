<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ auth()->user()->role->homePath() }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                @if (auth()->user()->isStaff())
                    <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navbar.item>
                    <flux:navbar.item icon="rectangle-stack" :href="route('exhibitions.index')" :current="request()->routeIs('exhibitions.*')" wire:navigate>
                        展示
                    </flux:navbar.item>
                    <flux:navbar.item icon="cog-6-tooth" :href="route('museum.settings')" :current="request()->routeIs('museum.settings')" wire:navigate>
                        館の設定
                    </flux:navbar.item>
                @endif
                @if (auth()->user()->isAdmin())
                    <flux:navbar.item icon="building-library" :href="route('admin.museums.index')" :current="request()->routeIs('admin.museums.*')" wire:navigate>
                        館
                    </flux:navbar.item>
                @endif
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ auth()->user()->role->homePath() }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    @if (auth()->user()->isStaff())
                        <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard')  }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('exhibitions.index')" :current="request()->routeIs('exhibitions.*')" wire:navigate>
                            展示
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('museum.settings')" :current="request()->routeIs('museum.settings')" wire:navigate>
                            館の設定
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->isAdmin())
                        <flux:sidebar.item icon="building-library" :href="route('admin.museums.index')" :current="request()->routeIs('admin.museums.*')" wire:navigate>
                            館
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
