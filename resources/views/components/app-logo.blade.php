@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Krakatau International Port" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-white ring-1 ring-slate-200">
            <img src="{{ asset('image/krakatau_logo.png') }}" alt="Krakatau International Port" class="size-6 object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Krakatau International Port" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-white ring-1 ring-slate-200">
            <img src="{{ asset('image/krakatau_logo.png') }}" alt="Krakatau International Port" class="size-6 object-contain" />
        </x-slot>
    </flux:brand>
@endif
