<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        @php
            $user = auth()->user();
            $menuGroups = collect(config('navigation'))
                ->map(function (array $items) use ($user): array {
                    return collect($items)
                        ->map(function (array $item) use ($user): ?array {
                            if (isset($item['children'])) {
                                $children = collect($item['children'])
                                    ->filter(fn (array $child): bool => $user->hasPermission($child['permission'] ?? ''))
                                    ->values()
                                    ->all();

                                return count($children) > 0 ? [...$item, 'children' => $children] : null;
                            }

                            return $user->hasPermission($item['permission'] ?? '') ? $item : null;
                        })
                        ->filter()
                        ->values()
                        ->all();
                })
                ->filter(fn (array $items): bool => count($items) > 0)
                ->all();
        @endphp

        <div class="app-shell min-h-screen bg-slate-50 lg:grid lg:grid-cols-[280px_1fr]" data-app-shell>
            <aside class="hidden border-r border-sky-900 bg-sky-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
                <div class="sidebar-header flex items-center justify-between gap-3 px-7 py-7">
                    <a href="{{ route('dashboard') }}" class="sidebar-brand ml-3 flex min-w-0 items-center" wire:navigate>
                        <img
                            src="{{ asset('image/esisman_logo.png') }}"
                            alt="E-SISMAN"
                            class="sidebar-brand-logo h-auto w-[140px] max-w-full object-contain"
                        >
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

                    <div class="sidebar-kip-brand flex w-full items-center justify-start gap-3 px-2.5 pt-5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                            <img
                                src="{{ asset('image/krakatau_logo.png') }}"
                                alt="Krakatau International Port"
                                class="size-7 object-contain"
                            >
                        </span>
                        <span class="sidebar-label grid leading-none">
                            <span class="text-sm font-extrabold uppercase text-white">Krakatau</span>
                            <span class="mt-1 text-[10px] font-bold uppercase text-white">International Port</span>
                        </span>
                    </div>
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

        <script>
            (() => {
                if (window.userSearchSelectReady) {
                    return;
                }

                window.userSearchSelectReady = true;

                const closePicker = (root) => {
                    root?.querySelector('[data-user-search-panel]')?.classList.add('hidden');
                    root?.querySelector('[data-user-search-trigger]')?.setAttribute('aria-expanded', 'false');
                };

                const openPicker = (root) => {
                    document.querySelectorAll('[data-user-search-select]').forEach((picker) => {
                        if (picker !== root) {
                            closePicker(picker);
                        }
                    });

                    root?.querySelector('[data-user-search-panel]')?.classList.remove('hidden');
                    root?.querySelector('[data-user-search-trigger]')?.setAttribute('aria-expanded', 'true');

                    const input = root?.querySelector('[data-user-search-input]');
                    input?.focus();
                    input?.select();
                };

                window.clearUserSearchSelect = (root) => {
                    if (!root) {
                        return;
                    }

                    const value = root.querySelector('[data-user-search-value]');
                    const initials = root.querySelector('[data-user-search-initials]');
                    const name = root.querySelector('[data-user-search-name]');
                    const meta = root.querySelector('[data-user-search-meta]');

                    if (value) {
                        value.value = '';
                    }

                    if (initials) {
                        initials.textContent = '?';
                        initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 ring-1 ring-slate-200';
                    }

                    if (name) {
                        name.textContent = root.dataset.placeholder || 'Pilih user';
                    }

                    if (meta) {
                        meta.textContent = '';
                        meta.classList.add('hidden');
                    }
                };

                window.setUserSearchSelect = (root, user) => {
                    if (!root || !user) {
                        return;
                    }

                    const value = root.querySelector('[data-user-search-value]');
                    const initials = root.querySelector('[data-user-search-initials]');
                    const name = root.querySelector('[data-user-search-name]');
                    const meta = root.querySelector('[data-user-search-meta]');

                    if (value) {
                        value.value = user.value || user.id || '';
                    }

                    if (initials) {
                        initials.textContent = user.initials || '?';
                        initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100';
                    }

                    if (name) {
                        name.textContent = user.name || root.dataset.placeholder || 'Pilih user';
                    }

                    if (meta) {
                        meta.textContent = user.meta || user.title || user.email || '';
                        meta.classList.toggle('hidden', !meta.textContent);
                    }
                };

                const syncPlaceholder = (root) => {
                    if (!root || root.dataset.placeholder) {
                        return;
                    }

                    root.dataset.placeholder = root.querySelector('[data-user-search-name]')?.textContent || 'Pilih user';
                };

                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('[data-user-search-trigger]');

                    if (trigger) {
                        const root = trigger.closest('[data-user-search-select]');
                        const panel = root?.querySelector('[data-user-search-panel]');

                        syncPlaceholder(root);

                        if (panel?.classList.contains('hidden')) {
                            openPicker(root);
                        } else {
                            closePicker(root);
                        }

                        return;
                    }

                    const option = event.target.closest('[data-user-search-option]');

                    if (option) {
                        const root = option.closest('[data-user-search-select]');
                        const value = root?.querySelector('[data-user-search-value]');

                        syncPlaceholder(root);
                        window.setUserSearchSelect(root, option.dataset);

                        if (value) {
                            value.dispatchEvent(new Event('input', { bubbles: true }));
                            value.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        closePicker(root);

                        root?.dispatchEvent(new CustomEvent('user-search-select:selected', {
                            bubbles: true,
                            detail: { ...option.dataset },
                        }));

                        if (root?.dataset.clearOnSelect === 'true') {
                            window.clearUserSearchSelect(root);
                        }

                        return;
                    }

                    document.querySelectorAll('[data-user-search-select]').forEach((root) => {
                        if (!root.contains(event.target)) {
                            closePicker(root);
                        }
                    });
                });

                document.addEventListener('input', (event) => {
                    const input = event.target.closest('[data-user-search-input]');

                    if (!input) {
                        return;
                    }

                    const root = input.closest('[data-user-search-select]');
                    const query = input.value.trim().toLowerCase();
                    let visibleCount = 0;

                    root?.querySelectorAll('[data-user-search-option]').forEach((option) => {
                        const isVisible = (option.dataset.search || '').includes(query);
                        option.classList.toggle('hidden', !isVisible);
                        visibleCount += isVisible ? 1 : 0;
                    });

                    root?.querySelector('[data-user-search-empty]')?.classList.toggle('hidden', visibleCount > 0);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        document.querySelectorAll('[data-user-search-select]').forEach(closePicker);
                    }
                });
            })();
        </script>
    </body>
</html>
