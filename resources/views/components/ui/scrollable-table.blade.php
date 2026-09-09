@props([
    'maxHeight' => '430px',
    'minWidth' => '760px',
    'horizontal' => true,
])

<x-ui.scroll-area :max-height="$maxHeight" @class([
    'overflow-x-auto' => $horizontal,
    'overflow-x-hidden' => ! $horizontal,
])>
    <table {{ $attributes->class(['ui-data-table w-full text-left text-sm'])->style([
        "min-width: {$minWidth}" => filled($minWidth),
    ]) }}>
        {{ $slot }}
    </table>
</x-ui.scroll-area>
