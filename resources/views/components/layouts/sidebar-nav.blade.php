@props([
    'groups',
    'mobile' => false,
])

@foreach ($groups as $heading => $items)
    <div class="{{ $mobile ? 'mt-4 first:mt-3' : 'mt-6 first:mt-2' }}">
        <p class="{{ $mobile ? '' : 'sidebar-label' }} px-3 text-xs font-extrabold uppercase tracking-wide text-white">{{ $heading }}</p>

        <div class="mt-2 space-y-1">
            @foreach ($items as $item)
                @php
                    $children = $item['children'] ?? [];
                    $hasChildren = count($children) > 0;
                    $active = $hasChildren
                        ? collect($children)->contains(fn ($child) => request()->routeIs($child['route']))
                        : request()->routeIs($item['route']);
                @endphp

                @if ($hasChildren)
                    <details class="group rounded-lg {{ $active ? 'bg-sky-900/70' : '' }}" @open($active)>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 px-3 text-sm font-semibold text-white [&::-webkit-details-marker]:hidden">
                            <flux:icon :name="$item['icon']" class="size-5 text-white" />
                            <span @class(['sidebar-label' => ! $mobile])>{{ $item['label'] }}</span>
                            <flux:icon name="chevron-down" class="ml-auto size-4 text-white transition group-open:rotate-180" />
                        </summary>

                        <div class="space-y-1 pb-1 pl-8 pr-2">
                            @foreach ($children as $child)
                                @php
                                    $childActive = request()->routeIs($child['route']);
                                @endphp

                                <a
                                    href="{{ route($child['route']) }}"
                                    class="flex min-h-9 items-center gap-2 rounded-md px-3 text-sm font-semibold transition {{ $childActive ? 'bg-white text-sky-800 shadow-sm' : 'text-white/90 hover:bg-sky-900 hover:text-white' }}"
                                    wire:navigate
                                    @if ($mobile) data-mobile-nav-close @endif
                                >
                                    <flux:icon :name="$child['icon']" class="size-4 {{ $childActive ? 'text-sky-700' : 'text-white' }}" />
                                    <span @class(['sidebar-label' => ! $mobile])>{{ $child['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a
                        href="{{ route($item['route']) }}"
                        class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ $active ? 'bg-white text-sky-800 shadow-sm' : 'text-white hover:bg-sky-900 hover:text-white' }}"
                        wire:navigate
                        @if ($mobile) data-mobile-nav-close @endif
                    >
                        <flux:icon :name="$item['icon']" class="size-5 {{ $active ? 'text-sky-700' : 'text-white' }}" />
                        <span @class(['sidebar-label' => ! $mobile])>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endforeach
