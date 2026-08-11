<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        @php
            $menuGroups = config('navigation');
        @endphp

        <div class="app-shell min-h-screen bg-slate-50 lg:grid lg:grid-cols-[280px_1fr]" data-app-shell>
            <aside class="hidden border-r border-slate-200 bg-white lg:flex lg:h-screen lg:flex-col">
                <div class="sidebar-header flex items-center justify-between gap-3 px-7 py-7">
                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" wire:navigate>
                        <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                            <img
                                src="{{ asset('image/krakatau_logo.png') }}"
                                alt="Krakatau International Port"
                                class="size-8 object-contain"
                            >
                        </span>
                        <span class="sidebar-label grid leading-none">
                            <span class="text-base font-extrabold uppercase text-slate-800">Krakatau</span>
                            <span class="mt-1 text-[11px] font-bold uppercase text-slate-500">International Port</span>
                        </span>
                    </a>

                    <button
                        type="button"
                        class="sidebar-toggle grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-slate-50 hover:text-slate-800"
                        data-sidebar-toggle
                        aria-label="Sembunyikan sidebar"
                        aria-expanded="true"
                    >
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                </div>

                <nav class="sidebar-scrollbar flex-1 overflow-y-auto px-5 pb-5">
                    @foreach ($menuGroups as $heading => $items)
                        <div class="mt-6 first:mt-2">
                            <p class="sidebar-label px-3 text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $heading }}</p>

                            <div class="mt-2 space-y-1">
                                @foreach ($items as $item)
                                    @php
                                        $active = request()->routeIs($item['route']);
                                    @endphp

                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ $active ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}"
                                        wire:navigate
                                    >
                                        <flux:icon :name="$item['icon']" class="size-5 {{ $active ? 'text-sky-600' : 'text-slate-400' }}" />
                                        <span class="sidebar-label">{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="sidebar-user border-t border-slate-200 p-5">
                    <div class="sidebar-label flex justify-center pb-5">
                        <img
                            src="{{ asset('image/esisman_logo_tight.png') }}"
                            alt="E-SISMAN"
                            class="h-auto w-[96px] max-w-full object-contain"
                        >
                    </div>

                    <flux:dropdown position="top" align="start">
                        <button
                            type="button"
                            class="sidebar-user-trigger flex w-full items-center gap-3 rounded-lg bg-slate-100 px-2.5 py-2 text-left transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-white"
                            data-test="sidebar-menu-button"
                        >
                            <span class="sidebar-account-avatar grid size-11 shrink-0 place-items-center rounded-lg bg-white text-xl font-bold leading-none text-slate-600 ring-1 ring-slate-200">
                                {{ auth()->user()->initials() }}
                            </span>
                            <span class="sidebar-label min-w-0">
                                <span class="block truncate text-xs text-slate-500">{{ auth()->user()->email }}</span>
                            </span>
                            <flux:icon name="chevron-up" class="sidebar-label ml-auto size-5 shrink-0 text-slate-400" />
                        </button>

                        <flux:menu>
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    class="size-9 shrink-0"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
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
                </div>
            </aside>

            <div class="min-w-0">
                <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:hidden">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2" wire:navigate>
                        <span class="grid size-9 place-items-center rounded-md bg-white shadow-sm ring-1 ring-slate-200">
                            <img    
                                src="{{ asset('image/krakatau_logo.png') }}"
                                alt="Krakatau International Port"
                                class="size-6 object-contain"
                            >
                        </span>
                        <span class="text-sm font-extrabold text-slate-800">E-SISMAN</span>
                    </a>
                </header>

                <main class="min-h-screen bg-slate-50 p-5 md:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts

        <script>
            (() => {
                const shell = document.querySelector('[data-app-shell]');
                const toggle = document.querySelector('[data-sidebar-toggle]');

                if (!shell || !toggle) return;

                const applyState = (collapsed) => {
                    shell.classList.toggle('is-sidebar-collapsed', collapsed);
                    toggle.setAttribute('aria-expanded', String(!collapsed));
                    toggle.setAttribute('aria-label', collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar');
                };

                applyState(localStorage.getItem('esisman-sidebar-collapsed') === 'true');

                toggle.addEventListener('click', () => {
                    const collapsed = !shell.classList.contains('is-sidebar-collapsed');
                    localStorage.setItem('esisman-sidebar-collapsed', String(collapsed));
                    applyState(collapsed);
                });
            })();
        </script>
    </body>
</html>
