@props([
    'maxHeight' => '430px',
    'minWidth' => '760px',
])

<x-ui.scroll-area :max-height="$maxHeight" class="overflow-x-auto">
    <table {{ $attributes->class(['w-full text-left text-sm'])->style([
        "min-width: {$minWidth}" => filled($minWidth),
    ]) }}>
        {{ $slot }}
    </table>
</x-ui.scroll-area>
