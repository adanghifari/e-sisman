@props([
    'as' => 'div',
    'maxHeight' => null,
])

@php
    $scrollAttributes = $attributes->class(['app-scrollbar overflow-y-auto'])->style([
        "max-height: {$maxHeight}" => filled($maxHeight),
    ]);
@endphp

@if ($as === 'nav')
    <nav {{ $scrollAttributes }}>
        {{ $slot }}
    </nav>
@else
    <div {{ $scrollAttributes }}>
        {{ $slot }}
    </div>
@endif
