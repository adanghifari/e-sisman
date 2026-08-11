@props([
    'label',
    'name',
    'accept' => null,
    'hint' => null,
])

<label class="block" data-file-upload>
    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>

    <span class="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-sky-300 hover:bg-sky-50">
        <span class="text-sm font-semibold text-slate-800" data-file-upload-name>
            Pilih file template
        </span>

        @if ($hint)
            <span class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</span>
        @endif
    </span>

    <input
        type="file"
        name="{{ $name }}"
        accept="{{ $accept }}"
        class="sr-only"
        data-file-upload-input
        {{ $attributes }}
    >
</label>

@once
    <script>
        (() => {
            document.addEventListener('change', (event) => {
                const input = event.target.closest('[data-file-upload-input]');

                if (! input) {
                    return;
                }

                const wrapper = input.closest('[data-file-upload]');
                const fileName = wrapper?.querySelector('[data-file-upload-name]');

                if (fileName) {
                    fileName.textContent = input.files?.[0]?.name || 'Pilih file template';
                }
            });
        })();
    </script>
@endonce
