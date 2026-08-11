@props([
    'active' => false,
    'name' => null,
    'label' => 'Status',
    'activeText' => 'Active',
    'inactiveText' => 'Inactive',
    'activeDescription' => null,
    'inactiveDescription' => null,
])

<div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
    <div>
        <p class="text-sm font-semibold text-slate-800">{{ $label }}</p>

        @if ($activeDescription || $inactiveDescription)
            <p class="text-xs text-slate-500">
                {{ $active ? $activeDescription : $inactiveDescription }}
            </p>
        @endif
    </div>

    <label class="inline-flex cursor-pointer items-center gap-3">
        <span class="text-sm font-semibold text-slate-700">{{ $active ? $activeText : $inactiveText }}</span>
        <input type="checkbox" name="{{ $name }}" @checked($active) {{ $attributes->class(['peer sr-only']) }}>
        <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-sky-600">
            <span class="absolute left-1 top-1 size-4 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
        </span>
    </label>
</div>
