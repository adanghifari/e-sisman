@props([
    'name',
    'users',
    'placeholder' => 'Pilih user',
])

<div
    {{ $attributes->class(['relative']) }}
    data-user-search-select
>
    <input type="hidden" name="{{ $name }}" data-user-search-value {{ $attributes->has('required') ? 'required' : '' }}>

    <button
        type="button"
        class="flex h-12 w-full items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm font-medium text-slate-600 outline-none transition hover:border-sky-300 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
        data-user-search-trigger
        aria-expanded="false"
    >
        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 ring-1 ring-slate-200" data-user-search-initials>
            ?
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold" data-user-search-name>{{ $placeholder }}</span>
            <span class="hidden truncate text-xs text-slate-500" data-user-search-meta></span>
        </span>
        <flux:icon name="chevron-down" class="size-5 shrink-0 text-slate-400" />
    </button>

    <div class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg" data-user-search-panel>
        <div class="border-b border-slate-100 p-2">
            <input
                type="search"
                placeholder="Cari nama, jabatan, atau email"
                class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600 outline-none transition placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:ring-2 focus:ring-sky-100"
                data-user-search-input
            >
        </div>

        <div class="max-h-72 overflow-y-auto py-1 app-scrollbar" data-user-search-options>
            @foreach ($users as $user)
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    data-user-search-option
                    data-value="{{ $user->id }}"
                    data-name="{{ $user->name }}"
                    data-meta="{{ $user->jabatan ?: $user->email }}"
                    data-email="{{ $user->email }}"
                    data-title="{{ $user->jabatan }}"
                    data-initials="{{ $user->initials() }}"
                    data-search="{{ \Illuminate\Support\Str::lower($user->name.' '.$user->email.' '.$user->jabatan) }}"
                >
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100">
                        {{ $user->initials() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-slate-900">{{ $user->name }}</span>
                        <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $user->jabatan ?: $user->email }}</span>
                    </span>
                </button>
            @endforeach

            <div class="hidden px-3 py-4 text-center text-sm font-medium text-slate-500" data-user-search-empty>
                User tidak ditemukan.
            </div>
        </div>
    </div>
</div>

@once
    <script>
        (() => {
            const closeUserSearchSelect = (root) => {
                const trigger = root.querySelector('[data-user-search-trigger]');
                const panel = root.querySelector('[data-user-search-panel]');

                panel?.classList.add('hidden');
                trigger?.setAttribute('aria-expanded', 'false');
            };

            const openUserSearchSelect = (root) => {
                const trigger = root.querySelector('[data-user-search-trigger]');
                const panel = root.querySelector('[data-user-search-panel]');
                const input = root.querySelector('[data-user-search-input]');

                document.querySelectorAll('[data-user-search-select]').forEach((picker) => {
                    if (picker !== root) {
                        closeUserSearchSelect(picker);
                    }
                });

                panel?.classList.remove('hidden');
                trigger?.setAttribute('aria-expanded', 'true');
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

                value.value = '';
                initials.textContent = '?';
                initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 ring-1 ring-slate-200';
                name.textContent = root.dataset.placeholder || 'Pilih user';
                meta.textContent = '';
                meta.classList.add('hidden');
            };

            window.setUserSearchSelect = (root, user) => {
                if (!root || !user) {
                    return;
                }

                const value = root.querySelector('[data-user-search-value]');
                const initials = root.querySelector('[data-user-search-initials]');
                const name = root.querySelector('[data-user-search-name]');
                const meta = root.querySelector('[data-user-search-meta]');

                value.value = user.value || user.id || '';
                initials.textContent = user.initials || '?';
                initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100';
                name.textContent = user.name || root.dataset.placeholder || 'Pilih user';
                meta.textContent = user.meta || user.title || user.email || '';
                meta.classList.toggle('hidden', !meta.textContent);
            };

            window.initUserSearchSelect = (root) => {
                if (!root || root.dataset.userSearchInitialized === 'true') {
                    return;
                }

                root.dataset.userSearchInitialized = 'true';

                const value = root.querySelector('[data-user-search-value]');
                const trigger = root.querySelector('[data-user-search-trigger]');
                const input = root.querySelector('[data-user-search-input]');
                const initials = root.querySelector('[data-user-search-initials]');
                const name = root.querySelector('[data-user-search-name]');
                const meta = root.querySelector('[data-user-search-meta]');
                const options = Array.from(root.querySelectorAll('[data-user-search-option]'));
                const empty = root.querySelector('[data-user-search-empty]');

                root.dataset.placeholder = name?.textContent || 'Pilih user';

                trigger?.addEventListener('click', () => {
                    const panel = root.querySelector('[data-user-search-panel]');
                    const isOpen = panel && !panel.classList.contains('hidden');

                    isOpen ? closeUserSearchSelect(root) : openUserSearchSelect(root);
                });

                input?.addEventListener('input', () => {
                    const query = input.value.trim().toLowerCase();
                    let visibleCount = 0;

                    options.forEach((option) => {
                        const isVisible = option.dataset.search.includes(query);
                        option.classList.toggle('hidden', !isVisible);
                        visibleCount += isVisible ? 1 : 0;
                    });

                    empty?.classList.toggle('hidden', visibleCount > 0);
                });

                options.forEach((option) => {
                    option.addEventListener('click', () => {
                        value.value = option.dataset.value || '';
                        initials.textContent = option.dataset.initials || '?';
                        initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100';
                        name.textContent = option.dataset.name || 'Pilih user';
                        meta.textContent = option.dataset.meta || '';
                        meta.classList.toggle('hidden', !option.dataset.meta);
                        closeUserSearchSelect(root);

                        root.dispatchEvent(new CustomEvent('user-search-select:selected', {
                            bubbles: true,
                            detail: {...option.dataset},
                        }));
                    });
                });
            };

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-user-search-select]').forEach((root) => {
                    if (!root.contains(event.target)) {
                        closeUserSearchSelect(root);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-user-search-select]').forEach(closeUserSearchSelect);
                }
            });

            document.querySelectorAll('[data-user-search-select]').forEach(window.initUserSearchSelect);
        })();
    </script>
@endonce
