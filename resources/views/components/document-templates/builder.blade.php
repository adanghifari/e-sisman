@props([
    'documentLevels',
    'canEdit' => false,
    'uploadLimits' => [],
])

@php
    $maxFiles = $uploadLimits['max_files'] ?? 10;
    $maxFileSizeKb = $uploadLimits['max_file_size_kb'] ?? 10240;
    $maxFileSizeMb = (int) ceil($maxFileSizeKb / 1024);
    $allowedExtensions = $uploadLimits['allowed_extensions'] ?? ['doc', 'docx'];
    $allowedExtensionText = strtoupper(implode(', ', $allowedExtensions));
@endphp

<div class="space-y-6" data-template-builder data-can-edit="{{ $canEdit ? 'true' : 'false' }}">
    <x-ui.page-header
        title="Template Dokumen"
        description="Atur template file yang digunakan saat pengajuan dokumen berdasarkan level dokumen."
    />

    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <x-ui.panel title="Level Dokumen" description="Pilih level dokumen yang ingin disetting." class="h-fit xl:sticky xl:top-8">
            <div class="mt-4 space-y-3">
                @foreach ($documentLevels as $levelKey => $level)
                    <button
                        type="button"
                        class="group w-full rounded-lg border border-slate-200 bg-white px-5 py-4 text-left transition hover:border-sky-200 hover:bg-sky-50 data-[active=true]:border-sky-300 data-[active=true]:bg-sky-50 data-[active=true]:shadow-sm"
                        data-template-level-option
                        data-level-key="{{ $levelKey }}"
                        data-level-name="{{ $level['name'] }}"
                    >
                        <span class="block font-semibold text-slate-900 group-data-[active=true]:text-sky-800">
                            {{ $level['name'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        </x-ui.panel>

        <div class="space-y-5">
            <x-ui.empty-state
                title="Pilih Level Dokumen"
                description="Form template akan muncul setelah level dokumen dipilih."
                data-template-empty-state
            />

            <x-ui.panel class="hidden" data-template-form-panel>
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Template Aktif</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950" data-template-level-title></h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500" data-template-mode-text>
                            Template ditampilkan dalam mode read-only.
                        </p>
                    </div>

                    @if ($canEdit)
                        <x-ui.action-button type="button" variant="secondary" data-template-edit-toggle>
                            Edit Template
                        </x-ui.action-button>
                    @endif
                </div>

                <div class="mt-5" data-template-read-panel>
                    <div class="hidden rounded-lg border border-slate-200 bg-slate-50 p-3" data-template-file-preview>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                    <flux:icon name="document-text" class="size-5" />
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900" data-template-file-name></p>
                                    <p class="mt-1 text-xs text-slate-500" data-template-file-meta></p>
                                </div>
                            </div>

                            <x-ui.action-button type="button" variant="secondary">
                                Buka File
                            </x-ui.action-button>
                        </div>
                    </div>

                    <x-ui.empty-state
                        title="Belum Ada File Template"
                        description="Template untuk level dokumen ini belum tersedia."
                        data-template-no-file
                    />
                </div>

                <form class="mt-5 hidden space-y-5" data-template-edit-form>
                    <input type="hidden" name="document_level" data-template-level-input>

                    <x-ui.input
                        label="Judul Template"
                        name="title"
                        placeholder="Contoh: Template Instruksi Kerja"
                        data-template-title
                        readonly
                    />

                    <x-ui.file-upload
                        label="File Template"
                        name="template_files[]"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        hint="Format: {{ $allowedExtensionText }}. Maksimal {{ $maxFiles }} file, ukuran maksimal {{ $maxFileSizeMb }} MB per file."
                        :multiple="true"
                        :max-files="$maxFiles"
                        :max-file-size-kb="$maxFileSizeKb"
                        disabled
                        data-template-file
                    />

                    <x-ui.textarea
                        label="Catatan Singkat (Opsional)"
                        name="notes"
                        rows="3"
                        placeholder="Tambahkan catatan singkat jika ada."
                        data-template-notes
                        readonly
                    />

                    <div class="hidden justify-end gap-2 border-t border-slate-100 pt-5" data-template-actions>
                        <x-ui.action-button type="button" data-template-save>
                            Simpan Template
                        </x-ui.action-button>
                    </div>
                </form>
            </x-ui.panel>
        </div>
    </div>
</div>

@once
    <script>
        (() => {
            document.querySelectorAll('[data-template-builder]').forEach((builder) => {
                const emptyState = builder.querySelector('[data-template-empty-state]');
                const panel = builder.querySelector('[data-template-form-panel]');
                const levelTitle = builder.querySelector('[data-template-level-title]');
                const levelInput = builder.querySelector('[data-template-level-input]');
                const titleInput = builder.querySelector('[data-template-title]');
                const notesInput = builder.querySelector('[data-template-notes]');
                const fileInput = builder.querySelector('[data-template-file]');
                const actions = builder.querySelector('[data-template-actions]');
                const modeText = builder.querySelector('[data-template-mode-text]');
                const editToggle = builder.querySelector('[data-template-edit-toggle]');
                const readPanel = builder.querySelector('[data-template-read-panel]');
                const editForm = builder.querySelector('[data-template-edit-form]');
                const filePreview = builder.querySelector('[data-template-file-preview]');
                const noFile = builder.querySelector('[data-template-no-file]');
                const fileName = builder.querySelector('[data-template-file-name]');
                const fileMeta = builder.querySelector('[data-template-file-meta]');
                const templatesByLevel = {};
                const canEdit = builder.dataset.canEdit === 'true';
                let activeLevelKey = null;
                let isEditing = false;

                const selectedFiles = () => Array.from(fileInput.files || []);

                const updateReadPanel = () => {
                    const files = selectedFiles();
                    const hasStoredFile = false;
                    const hasFile = files.length > 0 || hasStoredFile;

                    filePreview.classList.toggle('hidden', ! hasFile);
                    noFile.classList.toggle('hidden', hasFile);

                    if (files.length > 0) {
                        const totalSizeKb = files.reduce((total, file) => total + Math.ceil(file.size / 1024), 0);

                        fileName.textContent = files.length > 1
                            ? `${files.length} file template dipilih`
                            : files[0].name;
                        fileMeta.textContent = `${totalSizeKb} KB total`;
                    }
                };

                const setEditing = (editing) => {
                    isEditing = canEdit && editing;

                    titleInput.toggleAttribute('readonly', ! isEditing);
                    notesInput.toggleAttribute('readonly', ! isEditing);
                    fileInput.toggleAttribute('disabled', ! isEditing);
                    actions.classList.toggle('hidden', ! isEditing);
                    actions.classList.toggle('flex', isEditing);
                    readPanel.classList.toggle('hidden', isEditing);
                    editForm.classList.toggle('hidden', ! isEditing);

                    modeText.textContent = isEditing
                        ? 'Upload file Word. Maksimal {{ $maxFiles }} file, {{ $maxFileSizeMb }} MB per file.'
                        : 'Template ditampilkan dalam mode read-only.';

                    if (editToggle) {
                        editToggle.textContent = isEditing ? 'Selesai Edit' : 'Edit Template';
                    }

                    if (! isEditing) {
                        updateReadPanel();
                    }
                };

                const persistActiveTemplate = () => {
                    if (! activeLevelKey) {
                        return;
                    }

                    templatesByLevel[activeLevelKey] = {
                        title: titleInput.value,
                        notes: notesInput.value,
                    };
                };

                const renderTemplate = (template = {}) => {
                    titleInput.value = template.title || '';
                    notesInput.value = template.notes || '';
                    updateReadPanel();
                };

                const selectLevel = (option) => {
                    persistActiveTemplate();

                    activeLevelKey = option.dataset.levelKey;
                    levelInput.value = activeLevelKey;

                    builder.querySelectorAll('[data-template-level-option]').forEach((item) => {
                        item.dataset.active = String(item === option);
                    });

                    levelTitle.textContent = option.dataset.levelName;
                    renderTemplate(templatesByLevel[activeLevelKey]);
                    setEditing(false);

                    emptyState.classList.add('hidden');
                    panel.classList.remove('hidden');
                };

                builder.querySelectorAll('[data-template-level-option]').forEach((option) => {
                    option.addEventListener('click', () => selectLevel(option));
                });

                builder.querySelector('[data-template-save]').addEventListener('click', () => {
                    persistActiveTemplate();
                    setEditing(false);
                });

                if (editToggle) {
                    editToggle.addEventListener('click', () => setEditing(! isEditing));
                }

                panel.addEventListener('input', persistActiveTemplate);
                panel.addEventListener('change', () => {
                    persistActiveTemplate();
                    updateReadPanel();
                });

                setEditing(false);
            });
        })();
    </script>
@endonce
