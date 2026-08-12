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
            <aside class="hidden border-r border-sky-900 bg-sky-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
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
                            <span class="text-base font-extrabold uppercase text-white">Krakatau</span>
                            <span class="mt-1 text-[11px] font-bold uppercase text-white">International Port</span>
                        </span>
                    </a>

                    <button
                        type="button"
                        class="sidebar-toggle grid size-9 shrink-0 place-items-center rounded-lg border border-sky-700 bg-sky-900 text-white shadow-sm transition hover:bg-sky-800 hover:text-white"
                        data-sidebar-toggle
                        aria-label="Sembunyikan sidebar"
                        aria-expanded="true"
                    >
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                </div>

                <x-ui.scroll-area as="nav" class="sidebar-scrollbar flex-1 px-5 pb-5">
                    <x-layouts.sidebar-nav :groups="$menuGroups" />
                </x-ui.scroll-area>

                <div class="sidebar-user border-t border-sky-800 p-5">
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
                            class="sidebar-user-trigger flex w-full items-center gap-3 rounded-lg bg-sky-900 px-2.5 py-2 text-left transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-sky-950"
                            data-test="sidebar-menu-button"
                        >
                            <span class="sidebar-account-avatar grid size-11 shrink-0 place-items-center rounded-lg bg-white text-xl font-bold leading-none text-sky-800 ring-1 ring-sky-200">
                                {{ auth()->user()->initials() }}
                            </span>
                            <span class="sidebar-label min-w-0">
                                <span class="block truncate text-xs text-white">{{ auth()->user()->email }}</span>
                            </span>
                            <flux:icon name="chevron-up" class="sidebar-label ml-auto size-5 shrink-0 text-white" />
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
                <div class="fixed inset-x-0 top-0 z-30 lg:hidden">
                    <header class="flex h-16 items-center justify-between border-b border-sky-800 bg-sky-950 px-4 text-white shadow-sm">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2" wire:navigate>
                            <span class="grid size-9 place-items-center rounded-md bg-white shadow-sm ring-1 ring-slate-200">
                                <img
                                    src="{{ asset('image/krakatau_logo.png') }}"
                                    alt="Krakatau International Port"
                                    class="size-6 object-contain"
                                >
                            </span>
                            <span class="text-sm font-extrabold text-white">E-SISMAN</span>
                        </a>

                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-lg border border-sky-700 bg-sky-900 text-white shadow-sm transition hover:bg-sky-800"
                            data-mobile-nav-toggle
                            aria-label="Tampilkan menu"
                            aria-expanded="false"
                        >
                            <flux:icon name="bars-3" class="size-5" />
                        </button>
                    </header>

                    <div class="mobile-nav-panel border-b border-sky-800 bg-sky-950 text-white shadow-lg">
                        <x-ui.scroll-area max-height="calc(100vh - 4rem)" class="sidebar-scrollbar px-4 pb-4">
                            <x-layouts.sidebar-nav :groups="$menuGroups" mobile />
                        </x-ui.scroll-area>
                    </div>
                </div>

                <main class="min-h-screen bg-slate-50 p-5 pt-20 md:p-8 md:pt-20 lg:pt-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @if (session('document_success'))
            <x-ui.success-dialog
                :title="session('document_success.title')"
                :message="session('document_success.message')"
            />
        @endif

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

                if (!shell) return;

                if (toggle) {
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
                }

                const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
                const mobileCloseItems = document.querySelectorAll('[data-mobile-nav-close]');

                mobileToggle?.addEventListener('click', () => {
                    const isOpen = shell.classList.toggle('is-mobile-nav-open');
                    mobileToggle.setAttribute('aria-expanded', String(isOpen));
                    mobileToggle.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Tampilkan menu');
                });

                mobileCloseItems.forEach((element) => {
                    element.addEventListener('click', () => {
                        shell.classList.remove('is-mobile-nav-open');
                        mobileToggle?.setAttribute('aria-expanded', 'false');
                        mobileToggle?.setAttribute('aria-label', 'Tampilkan menu');
                    });
                });
            })();
        </script>
    </body>
</html>
