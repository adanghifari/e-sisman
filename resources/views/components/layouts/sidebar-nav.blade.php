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
                    $active = request()->routeIs($item['route']);
                @endphp

                <a
                    href="{{ route($item['route']) }}"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ $active ? 'bg-white text-sky-800 shadow-sm' : 'text-white hover:bg-sky-900 hover:text-white' }}"
                    wire:navigate
                    @if ($mobile) data-mobile-nav-close @endif
                >
                    <flux:icon :name="$item['icon']" class="size-5 {{ $active ? 'text-sky-700' : 'text-white' }}" />
                    <span @class(['sidebar-label' => ! $mobile])>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach
