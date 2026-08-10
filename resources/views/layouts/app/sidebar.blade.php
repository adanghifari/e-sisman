<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        @php
            $menuGroups = config('navigation');
        @endphp

        <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-[280px_1fr]">
            <aside class="hidden border-r border-slate-200 bg-white lg:flex lg:h-screen lg:flex-col">
                <div class="px-7 py-7">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3" wire:navigate>
                        <span class="grid size-11 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                            <svg class="size-8" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 4h15.8L4 21.2V4Z" fill="#00A6D6"/>
                                <path d="M23.5 4H40L21 22.7 40 40H23.1L8.6 25.8 23.5 4Z" fill="#0086BF"/>
                                <path d="M4 25.4 18.7 40H4V25.4Z" fill="#00A6D6"/>
                            </svg>
                        </span>
                        <span class="grid leading-none">
                            <span class="text-base font-extrabold uppercase text-slate-800">Krakatau</span>
                            <span class="mt-1 text-[11px] font-bold uppercase text-slate-500">International Port</span>
                        </span>
                    </a>
                </div>

                <nav class="flex-1 overflow-y-auto px-5 pb-5">
                    @foreach ($menuGroups as $heading => $items)
                        <div class="mt-6 first:mt-2">
                            <p class="px-3 text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ $heading }}</p>

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
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-slate-200 p-5">
                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                        <div class="grid size-9 place-items-center rounded-md bg-slate-700 text-sm font-bold text-white">
                            {{ auth()->user()->initials() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:hidden">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2" wire:navigate>
                        <span class="grid size-9 place-items-center rounded-md bg-white shadow-sm ring-1 ring-slate-200">
                            <svg class="size-6" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 4h15.8L4 21.2V4Z" fill="#00A6D6"/>
                                <path d="M23.5 4H40L21 22.7 40 40H23.1L8.6 25.8 23.5 4Z" fill="#0086BF"/>
                                <path d="M4 25.4 18.7 40H4V25.4Z" fill="#00A6D6"/>
                            </svg>
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
    </body>
</html>
