@props([
    'label',
    'name',
    'accept' => null,
    'hint' => null,
    'multiple' => false,
    'maxFiles' => null,
    'maxFileSizeKb' => null,
    'existingFiles' => collect(),
])

@php
    $inputId = 'file-upload-'.str_replace(['[', ']'], '', $name).'-'.uniqid();
@endphp

<div
    class="block"
    data-file-upload
    @if ($maxFiles) data-max-files="{{ $maxFiles }}" @endif
    @if ($maxFileSizeKb) data-max-file-size-kb="{{ $maxFileSizeKb }}" @endif
>
    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>

    <label
        for="{{ $inputId }}"
        class="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-sky-300 hover:bg-sky-50 data-[dragging=true]:border-sky-400 data-[dragging=true]:bg-sky-50"
        data-file-upload-dropzone
    >
        <span class="flex size-12 items-center justify-center rounded-full border-4 border-slate-400 text-slate-400">
            <flux:icon name="arrow-down-tray" class="size-6" />
        </span>

        <span class="mt-4 text-base font-semibold text-slate-600" data-file-upload-name>
            Drag and drop files here or click to choose.
        </span>

        @if ($hint)
            <span class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</span>
        @endif

        <span class="mt-2 hidden text-xs font-semibold text-red-600" data-file-upload-error></span>
    </label>

    <div
        @class([
            'mt-3 rounded-lg border border-slate-200 bg-white p-4',
            'hidden' => collect($existingFiles)->isEmpty(),
            'grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5' => collect($existingFiles)->isNotEmpty(),
        ])
        data-file-upload-list
    >
        @foreach (collect($existingFiles) as $file)
            @php
                $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'file');
                $isPdf = $extension === 'pdf';
                $size = (int) ($file['size'] ?? 0);
                $sizeKb = (int) ceil($size / 1024);
                $formattedSize = $sizeKb >= 1024
                    ? number_format($sizeKb / 1024, 1).' MB'
                    : $sizeKb.' KB';
            @endphp
            <div class="group relative min-w-0 rounded-lg border border-slate-200 bg-white p-3 text-center transition hover:border-sky-200 hover:shadow-sm" data-existing-file-item>
                <input type="hidden" value="{{ $file['id'] ?? '' }}" data-existing-file-id>
                <div @class([
                    'relative mx-auto flex h-20 w-16 items-center justify-center rounded-md border text-sm font-bold shadow-sm',
                    'border-red-100 bg-red-50 text-red-600' => $isPdf,
                    'border-sky-100 bg-sky-50 text-sky-700' => ! $isPdf,
                ])>
                    {{ $isPdf ? 'PDF' : 'DOC' }}
                </div>
                <span class="mt-3 block truncate text-sm font-semibold text-red-700">{{ $file['name'] ?? '-' }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ $formattedSize }}</span>
                <button type="button" class="absolute right-2 top-2 inline-flex size-7 items-center justify-center rounded-full border border-red-200 bg-white text-sm font-bold text-red-600 opacity-0 shadow-sm transition hover:bg-red-50 group-hover:opacity-100 focus:opacity-100" data-existing-file-remove aria-label="Hapus {{ $file['name'] ?? 'file' }}">x</button>
            </div>
        @endforeach
    </div>

    <input
        id="{{ $inputId }}"
        type="file"
        name="{{ $name }}"
        accept="{{ $accept }}"
        @if ($multiple) multiple @endif
        class="sr-only"
        data-file-upload-input
        {{ $attributes }}
    >
</div>

@once
    <script>
        (() => {
            const syncInputFiles = (input, files) => {
                const dataTransfer = new DataTransfer();

                files.forEach((file) => dataTransfer.items.add(file));
                input.files = dataTransfer.files;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            const formatFileSize = (file) => {
                const sizeKb = Math.ceil(file.size / 1024);

                return sizeKb >= 1024
                    ? `${(sizeKb / 1024).toFixed(1)} MB`
                    : `${sizeKb} KB`;
            };

            const acceptedFileTypes = (input) => (input.getAttribute('accept') || '')
                .split(',')
                .map((type) => type.trim().toLowerCase())
                .filter(Boolean);

            const fileMatchesAccept = (file, acceptedTypes) => {
                if (acceptedTypes.length === 0) {
                    return true;
                }

                const fileName = file.name.toLowerCase();
                const fileType = (file.type || '').toLowerCase();

                return acceptedTypes.some((acceptedType) => {
                    if (acceptedType.startsWith('.')) {
                        return fileName.endsWith(acceptedType);
                    }

                    if (acceptedType.endsWith('/*')) {
                        return fileType.startsWith(acceptedType.slice(0, -1));
                    }

                    return fileType === acceptedType;
                });
            };

            const formatAcceptedTypes = (acceptedTypes) => acceptedTypes
                .map((type) => type.startsWith('.') ? type.slice(1).toUpperCase() : type)
                .join(', ');

            const clearRejectedFiles = (input, fileName, list) => {
                input.value = '';

                if (fileName) {
                    fileName.textContent = 'Drag and drop files here or click to choose.';
                }

                if (list) {
                    list.innerHTML = '';
                    list.className = 'mt-3 hidden rounded-lg border border-slate-200 bg-white p-4';
                }
            };

            const renderFileList = (input) => {
                const wrapper = input.closest('[data-file-upload]');
                const fileName = wrapper?.querySelector('[data-file-upload-name]');
                const error = wrapper?.querySelector('[data-file-upload-error]');
                const list = wrapper?.querySelector('[data-file-upload-list]');
                const maxFiles = Number(wrapper?.dataset.maxFiles || 0);
                const maxFileSizeKb = Number(wrapper?.dataset.maxFileSizeKb || 0);
                const files = Array.from(input.files || []);
                const acceptedTypes = acceptedFileTypes(input);

                if (error) {
                    error.textContent = '';
                    error.classList.add('hidden');
                }

                if (maxFiles && files.length > maxFiles) {
                    clearRejectedFiles(input, fileName, list);

                    if (error) {
                        error.textContent = `Maksimal ${maxFiles} file.`;
                        error.classList.remove('hidden');
                    }

                    return;
                }

                const invalidTypeFile = files.find((file) => !fileMatchesAccept(file, acceptedTypes));

                if (invalidTypeFile) {
                    clearRejectedFiles(input, fileName, list);

                    if (error) {
                        error.textContent = `File ${invalidTypeFile.name} ditolak. Format yang diperbolehkan: ${formatAcceptedTypes(acceptedTypes)}.`;
                        error.classList.remove('hidden');
                    }

                    return;
                }

                const oversizedFile = files.find((file) => maxFileSizeKb && file.size > maxFileSizeKb * 1024);

                if (oversizedFile) {
                    clearRejectedFiles(input, fileName, list);

                    if (error) {
                        error.textContent = `Ukuran maksimal per file ${Math.round(maxFileSizeKb / 1024)} MB.`;
                        error.classList.remove('hidden');
                    }

                    return;
                }

                if (fileName) {
                    fileName.textContent = files.length > 0
                        ? `${files.length} file dipilih`
                        : 'Drag and drop files here or click to choose.';
                }

                if (list) {
                    list.querySelectorAll('[data-selected-file-item]').forEach((item) => item.remove());
                    const hasExistingFiles = list.querySelector('[data-existing-file-item]') !== null;
                    list.className = 'mt-3 hidden rounded-lg border border-slate-200 bg-white p-4';

                    if (files.length > 0 || hasExistingFiles) {
                        list.classList.remove('hidden');
                        list.classList.add('grid', 'grid-cols-2', 'gap-4', 'sm:grid-cols-3', 'lg:grid-cols-5');
                    }

                    files.forEach((file, index) => {
                        const extension = file.name.split('.').pop()?.toLowerCase() || 'file';
                        const isPdf = extension === 'pdf';
                        const item = document.createElement('div');
                        item.className = 'group relative min-w-0 rounded-lg border border-slate-200 bg-white p-3 text-center transition hover:border-sky-200 hover:shadow-sm';
                        item.dataset.selectedFileItem = '';

                        const icon = document.createElement('div');
                        icon.className = [
                            'relative mx-auto flex h-20 w-16 items-center justify-center rounded-md border text-sm font-bold shadow-sm',
                            isPdf ? 'border-red-100 bg-red-50 text-red-600' : 'border-sky-100 bg-sky-50 text-sky-700',
                        ].join(' ');
                        icon.textContent = isPdf ? 'PDF' : 'DOC';

                        const name = document.createElement('span');
                        name.className = 'mt-3 block truncate text-sm font-semibold text-red-700';
                        name.textContent = file.name;

                        const meta = document.createElement('span');
                        meta.className = 'mt-1 block text-xs text-slate-500';
                        meta.textContent = formatFileSize(file);

                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'absolute right-2 top-2 inline-flex size-7 items-center justify-center rounded-full border border-red-200 bg-white text-sm font-bold text-red-600 opacity-0 shadow-sm transition hover:bg-red-50 group-hover:opacity-100 focus:opacity-100';
                        removeButton.setAttribute('aria-label', `Hapus ${file.name}`);
                        removeButton.textContent = 'x';
                        removeButton.addEventListener('click', () => {
                            const nextFiles = Array.from(input.files || []).filter((_, fileIndex) => fileIndex !== index);

                            syncInputFiles(input, nextFiles);
                            renderFileList(input);
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                        item.append(icon, name, meta, removeButton);
                        list.append(item);
                    });
                }
            };

            document.addEventListener('change', (event) => {
                const input = event.target.closest('[data-file-upload-input]');

                if (! input) {
                    return;
                }

                renderFileList(input);
            });

            document.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-existing-file-remove]');

                if (!removeButton) {
                    return;
                }

                const item = removeButton.closest('[data-existing-file-item]');
                const list = removeButton.closest('[data-file-upload-list]');
                const wrapper = removeButton.closest('[data-file-upload]');
                const fileId = item?.querySelector('[data-existing-file-id]')?.value;

                if (fileId) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'remove_existing_files[]';
                    input.value = fileId;
                    wrapper?.append(input);
                }

                item?.remove();

                const hasFiles = (list?.querySelectorAll('[data-existing-file-item], [data-selected-file-item]').length ?? 0) > 0;
                list?.classList.toggle('hidden', !hasFiles);

                if (!hasFiles) {
                    list?.classList.remove('grid', 'grid-cols-2', 'gap-4', 'sm:grid-cols-3', 'lg:grid-cols-5');
                }
            });

            document.addEventListener('dragover', (event) => {
                const dropzone = event.target.closest('[data-file-upload-dropzone]');

                if (! dropzone) {
                    return;
                }

                event.preventDefault();
                dropzone.dataset.dragging = 'true';
            });

            document.addEventListener('dragleave', (event) => {
                const dropzone = event.target.closest('[data-file-upload-dropzone]');

                if (! dropzone || dropzone.contains(event.relatedTarget)) {
                    return;
                }

                dropzone.dataset.dragging = 'false';
            });

            document.addEventListener('drop', (event) => {
                const dropzone = event.target.closest('[data-file-upload-dropzone]');

                if (! dropzone) {
                    return;
                }

                event.preventDefault();
                dropzone.dataset.dragging = 'false';

                const wrapper = dropzone.closest('[data-file-upload]');
                const input = wrapper?.querySelector('[data-file-upload-input]');

                if (! input || input.disabled) {
                    return;
                }

                syncInputFiles(input, Array.from(event.dataTransfer.files || []));
                renderFileList(input);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        })();
    </script>
@endonce
