@props([
    'src',
    'heightClass' => 'min-h-[760px] xl:h-[82vh]',
])

<div class="bg-white" data-lazy-pdf-preview>
    <div class="flex items-center justify-center bg-slate-50 px-4 py-4" data-lazy-pdf-placeholder>
        <button
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-100"
            data-lazy-pdf-load
            data-src="{{ $src }}"
        >
            <flux:icon name="eye" class="size-5" data-lazy-pdf-idle-icon />
            <span
                class="hidden size-5 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600"
                aria-hidden="true"
                data-lazy-pdf-loading-icon
            ></span>
            <span data-lazy-pdf-label>Lihat Dokumen</span>
        </button>
    </div>

    <iframe
        title="Preview dokumen PDF"
        class="hidden {{ $heightClass }} w-full bg-white motion-safe:animate-[lazy-pdf-expand_180ms_ease-out]"
        data-lazy-pdf-frame
    ></iframe>
</div>

@once
    <style>
        @keyframes lazy-pdf-expand {
            from {
                max-height: 0;
                opacity: 0;
                transform: translateY(-0.25rem);
            }

            to {
                max-height: 90vh;
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
        (() => {
            if (window.lazyPdfPreviewReady) {
                return;
            }

            window.lazyPdfPreviewReady = true;

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-lazy-pdf-load]');

                if (!button) {
                    return;
                }

                const root = button.closest('[data-lazy-pdf-preview]');
                const frame = root?.querySelector('[data-lazy-pdf-frame]');
                const placeholder = root?.querySelector('[data-lazy-pdf-placeholder]');
                const idleIcon = button.querySelector('[data-lazy-pdf-idle-icon]');
                const loadingIcon = button.querySelector('[data-lazy-pdf-loading-icon]');
                const label = button.querySelector('[data-lazy-pdf-label]');
                const source = button.dataset.src;

                if (!frame || !source) {
                    return;
                }

                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-80');
                idleIcon?.classList.add('hidden');
                loadingIcon?.classList.remove('hidden');

                if (label) {
                    label.textContent = 'Memuat...';
                }

                frame.addEventListener('load', () => {
                    frame.classList.remove('hidden');
                    placeholder?.remove();
                }, { once: true });

                frame.src = source;
            });
        })();
    </script>
@endonce
