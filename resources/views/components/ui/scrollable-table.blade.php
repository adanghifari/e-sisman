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

@once
    <script>
        (() => {
            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('[data-table-row-toggle]');

                if (! toggle) {
                    return;
                }

                const target = toggle.dataset.tableRowToggle;
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                const table = toggle.closest('table') ?? document;

                toggle.setAttribute('aria-expanded', String(! expanded));
                toggle.querySelector('svg')?.classList.toggle('rotate-90', ! expanded);

                table.querySelectorAll('[data-table-row-target]').forEach((row) => {
                    if (row.dataset.tableRowTarget === target) {
                        row.classList.toggle('hidden', expanded);
                    }
                });
            });
        })();
    </script>
@endonce
