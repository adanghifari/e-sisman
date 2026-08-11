@props([
    'title',
    'description' => null,
    'closeAction' => null,
    'maxWidth' => '2xl',
])

@php
    $widthClass = [
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ][$maxWidth] ?? 'max-w-2xl';
    $titleId = 'modal-title-'.str()->uuid();
@endphp

<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-6">
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        tabindex="-1"
        {{ $attributes->class(['w-full rounded-lg bg-white shadow-xl', $widthClass]) }}
    >
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <h2 id="{{ $titleId }}" class="text-lg font-semibold text-slate-900">{{ $title }}</h2>

                @if ($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>

            @if ($closeAction)
                <x-ui.icon-button
                    icon="x-mark"
                    label="Tutup"
                    variant="ghost"
                    wire:click="{{ $closeAction }}"
                />
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
