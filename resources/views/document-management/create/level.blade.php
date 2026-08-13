<x-layouts::app :title="__('Tambah Dokumen')">
    @php
        $levelKey = request()->route('level');
        $level = config("document-levels.{$levelKey}");
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
        ];
        $documentPrefixes = [
            'level-1' => 'SM',
            'level-2' => 'PS',
            'level-3' => 'IK',
        ];

        $ownerLabel = $levelKey === 'level-1' ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
        $documentTitle = \Illuminate\Support\Str::after($level['name'], ': ');
        $documentLevelRecord = \Illuminate\Support\Facades\Schema::hasTable('m_document_levels')
            ? \App\Models\DocumentLevel::query()->where('kode', $levelKey)->first()
            : null;
        $levelDisplayValue = $documentLevelRecord
            ? $documentLevelRecord->nama_level.' : '.\Illuminate\Support\Str::after($documentLevelRecord->nama_dokumen, ': ')
            : $level['badge'].' : '.$documentTitle;
        $businessProcesses = \Illuminate\Support\Facades\Schema::hasTable('m_proses_bisnis')
            ? \App\Models\BusinessProcess::query()->active()->orderBy('nama_proses_bisnis')->get()
            : collect();
        $businessFunctions = \Illuminate\Support\Facades\Schema::hasTable('m_proses_fungsi')
            ? \App\Models\BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get()
            : collect();
        $departments = \Illuminate\Support\Facades\Schema::hasTable('departments')
            ? \App\Models\Department::query()->active()->orderBy('nama_department')->get()
            : collect();
        $procedureLevelId = \Illuminate\Support\Facades\Schema::hasTable('m_document_levels')
            ? \App\Models\DocumentLevel::query()->where('kode', 'level-2')->value('id')
            : null;
        $approvedStatusId = \Illuminate\Support\Facades\Schema::hasTable('m_status_document')
            ? \App\Models\StatusDocument::query()->where('nama_status', \App\Models\StatusDocument::APPROVED)->value('id')
            : null;
        $procedureReferences = ($levelKey === 'level-3' && $procedureLevelId && $approvedStatusId)
            ? \App\Models\Document::query()
                ->select([
                    'id',
                    'nomor_dokumen',
                    'nama_dokumen',
                    'm_proses_bisnis_id',
                    'm_proses_fungsi_id',
                ])
                ->where('m_document_level_id', $procedureLevelId)
                ->where('m_status_document_id', $approvedStatusId)
                ->orderBy('nomor_dokumen')
                ->get()
            : collect();
        $documentNumberSuggestions = ($documentLevelRecord && in_array($levelKey, ['level-2', 'level-3'], true))
            ? \App\Models\Document::query()
                ->select(['m_proses_bisnis_id', 'm_proses_fungsi_id', 'nomor_revisi', 'nomor_dokumen'])
                ->where('m_document_level_id', $documentLevelRecord->id)
                ->whereNull('revised_from')
                ->where('nomor_revisi', 0)
                ->whereNotNull('nomor_dokumen')
                ->get()
                ->groupBy(fn ($document) => $document->m_proses_bisnis_id.'-'.$document->m_proses_fungsi_id)
                ->map(fn ($documents) => str_pad((string) ($documents->count() + 1), 2, '0', STR_PAD_LEFT))
                ->all()
            : [];
        $departmentOptions = $departments
            ->map(fn ($department) => [
                'value' => $department->id,
                'label' => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department,
            ])
            ->values();
        $selectedBusinessProcess = $businessProcesses->firstWhere('id', (int) old('m_proses_bisnis_id'));
        $documentNumberProcessCode = $selectedBusinessProcess?->kode ?: 'SMR';
        $documentNumberSegments = match ($levelKey) {
            'level-2' => [['value' => $documentNumberProcessCode, 'target' => 'business-process']],
            'level-3' => ['XXX', 'YY'],
            default => [],
        };
        $assignableUsers = \App\Models\User::query()
            ->with('department')
            ->orderBy('name')
            ->get();
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.create') }}" class="transition hover:text-sky-700" wire:navigate>Tambah Dokumen</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $level['badge'] }}</span>
        </nav>

        <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
            {{ $levelKey === 'level-1' ? 'Import' : 'Tambah' }} Dokumen Level {{ $levelNumbers[$levelKey] }} : {{ $documentTitle }}
        </h1>

        @if ($levelKey === 'level-1')
            <form method="POST" action="{{ route('documents.store', $levelKey) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                @csrf

                <div class="space-y-6">
                    <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Informasi Dokumen</h2>
                        </div>

                        <div class="px-6 py-6">
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                <input
                                    type="text"
                                    name="nama_dokumen"
                                    value="{{ old('nama_dokumen') }}"
                                    placeholder="Masukan nama dokumen"
                                    required
                                    @class([
                                        'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                        'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                        'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                    ])
                                >
                                @error('nama_dokumen')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Upload Dokumen</h2>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <x-ui.file-upload
                                label="Import Dokumen"
                                name="imported_document"
                                accept=".pdf,application/pdf"
                                hint="Format PDF."
                                :max-files="1"
                                :max-file-size-kb="10240"
                                required
                            />
                            @error('imported_document')
                                <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                                <textarea
                                    name="catatan_revisi"
                                    rows="5"
                                    placeholder="Tambahkan catatan dokumen"
                                    class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >{{ old('catatan_revisi') }}</textarea>
                                @error('catatan_revisi')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:sticky xl:top-8">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4">
                                <input type="text" value="{{ $documentPrefixes[$levelKey] }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                <span class="text-lg font-semibold text-slate-500">-</span>
                                <input type="text" name="nomor_dokumen_suffix" value="{{ old('nomor_dokumen_suffix') }}" required class="h-14 w-full rounded-lg border border-slate-300 bg-white px-3 text-center text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                            </div>
                            @error('nomor_dokumen_suffix')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input
                                type="text"
                                name="nomor_revisi"
                                value="{{ old('nomor_revisi') }}"
                                class="h-14 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                            @error('nomor_revisi')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <x-ui.date-input
                            label="Tanggal Terbit"
                            name="tanggal_terbit"
                            :value="old('tanggal_terbit')"
                        />
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                            Import Dokumen
                        </button>
                    </div>
                </aside>
            </form>
        @else
            <form method="POST" action="{{ route('documents.store', $levelKey) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_520px]">
                @csrf

                <div class="space-y-6">
                    <x-documents.form-section title="Informasi Dokumen">
                        <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                <input
                                    type="text"
                                    name="nama_dokumen"
                                    value="{{ old('nama_dokumen') }}"
                                    placeholder="Masukan nama dokumen"
                                    required
                                    @class([
                                        'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                        'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                        'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                    ])
                                >
                                @error('nama_dokumen')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>

                            <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">

                            @if ($levelKey === 'level-3')
                                <label class="block md:col-span-2">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Pilih Dokumen Level II: Prosedur</span>
                                    <select name="reference" class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option value="">-Pilih-</option>
                                        @foreach ($procedureReferences as $procedureReference)
                                            <option
                                                value="{{ $procedureReference->id }}"
                                                data-business-process-id="{{ $procedureReference->m_proses_bisnis_id }}"
                                                data-business-function-id="{{ $procedureReference->m_proses_fungsi_id }}"
                                                @selected((string) old('reference') === (string) $procedureReference->id)
                                            >
                                                {{ $procedureReference->nomor_dokumen ?: '-' }} - {{ $procedureReference->nama_dokumen }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reference')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </label>
                            @endif

                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                                <select name="m_proses_bisnis_id" required class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                    <option value="">-Pilih-</option>
                                    @foreach ($businessProcesses as $businessProcess)
                                        <option
                                            value="{{ $businessProcess->id }}"
                                            data-process-code="{{ $businessProcess->kode }}"
                                            @selected((string) old('m_proses_bisnis_id') === (string) $businessProcess->id)
                                        >
                                            {{ $businessProcess->nama_proses_bisnis }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('m_proses_bisnis_id')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>

                            <x-ui.multi-select
                                label="Department Terkait"
                                name="department_ids"
                                :options="$departmentOptions"
                                selected-placeholder="Tambah Department"
                                required
                                class="md:col-span-1"
                            />

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                <select name="m_proses_fungsi_id" required class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                    <option value="">-Pilih-</option>
                                    @foreach ($businessFunctions as $businessFunction)
                                        <option value="{{ $businessFunction->id }}" @selected((string) old('m_proses_fungsi_id') === (string) $businessFunction->id)>
                                            {{ $businessFunction->nama_proses_fungsi }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('m_proses_fungsi_id')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>
                    </x-documents.form-section>

                    <x-documents.official-preparer :label="$ownerLabel" :users="$assignableUsers" />

                    <x-documents.form-section title="Isi Dokumen" icon="cloud-arrow-up">
                        <div class="space-y-6 px-6 py-6">
                            <x-documents.upload-toggle-card
                                title="Template Dokumen yang Sudah Diisi"
                                button-label="Upload Template"
                                tone="sky"
                            >
                                <x-ui.file-upload
                                    label="Upload Template Terisi"
                                    name="filled_template"
                                    accept=".pdf,application/pdf"
                                    hint="Format PDF."
                                    :max-files="1"
                                    :max-file-size-kb="10240"
                                    required
                                />

                                @error('filled_template')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </x-documents.upload-toggle-card>

                            <x-documents.upload-toggle-card
                                title="Daftar Dokumen"
                                button-label="Tambah Dokumen"
                                badge="Lampiran"
                            >
                                <x-ui.file-upload
                                    label="Upload Lampiran"
                                    name="attachments[]"
                                    accept=".pdf,application/pdf"
                                    hint="Bisa lebih dari satu file. Format PDF."
                                    multiple
                                    :max-files="10"
                                    :max-file-size-kb="10240"
                                />

                                @error('attachments')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                                @error('attachments.*')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </x-documents.upload-toggle-card>
                        </div>
                    </x-documents.form-section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-8">
                    <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <x-documents.document-number-input
                                :prefix="$documentPrefixes[$levelKey]"
                                :segments="$documentNumberSegments"
                            />

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                                <input
                                    type="text"
                                    value="00.00"
                                    readonly
                                    class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600"
                                >
                            </label>

                            <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="arrow-path" class="size-6 text-slate-700" />
                                    <span>Status</span>
                                    <span class="ml-auto rounded-full bg-slate-200 px-3 py-1 text-sm font-bold text-slate-700">Draft</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <flux:icon name="calendar-days" class="size-6 text-slate-700" />
                                    <span>Tanggal Pengajuan</span>
                                    <span class="ml-auto text-slate-500">-</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <flux:icon name="calendar" class="size-6 text-slate-700" />
                                    <span>Tanggal Terbit</span>
                                    <span class="ml-auto text-slate-500">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 border-t border-dashed border-slate-200 px-6 py-5 sm:grid-cols-2">
                            <button type="submit" name="submit_action" value="draft" class="inline-flex h-12 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50">
                                Simpan Draft
                            </button>
                            <button type="submit" name="submit_action" value="submit" class="inline-flex h-12 items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                                Submit Dokumen
                            </button>
                        </div>
                    </section>
                </aside>
            </form>
        @endif
    </div>

    @once
        <script>
            (() => {
                const documentNumberSuggestions = @json($documentNumberSuggestions);

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-document-upload-trigger]');

                    if (!button) {
                        return;
                    }

                    const root = button.closest('[data-document-upload]');
                    const panel = root?.querySelector('[data-document-upload-panel]');

                    if (!panel) {
                        return;
                    }

                    panel.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                    button.classList.add('hidden');
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target.closest('form');

                    if (!form) {
                        return;
                    }

                    const emptyPicker = Array.from(form.querySelectorAll('[data-user-search-select]'))
                        .find((picker) => {
                            const value = picker.querySelector('[data-user-search-value]');

                            return value?.required && !value.value;
                        });

                    if (!emptyPicker) {
                        return;
                    }

                    event.preventDefault();
                    window.initUserSearchSelect?.(emptyPicker);
                    const trigger = emptyPicker.querySelector('[data-user-search-trigger]');
                    trigger?.focus();
                    trigger?.classList.add('border-red-300', 'ring-2', 'ring-red-100');
                });

                document.addEventListener('change', (event) => {
                    const select = event.target.closest('select[name="m_proses_bisnis_id"]');

                    if (!select) {
                        return;
                    }

                    const segment = document.querySelector('[data-document-number-segment="business-process"]');
                    const selectedOption = select.selectedOptions[0];
                    const processCode = selectedOption?.dataset.processCode;

                    if (segment && processCode) {
                        segment.value = processCode;
                    }
                });

                const syncProcedureReferenceOptions = (form) => {
                    const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                    const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                    const referenceSelect = form.querySelector('select[name="reference"]');

                    if (!processSelect || !functionSelect || !referenceSelect) {
                        return;
                    }

                    const processId = processSelect.value;
                    const functionId = functionSelect.value;
                    let selectedReferenceStillValid = referenceSelect.value === '';

                    Array.from(referenceSelect.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;

                            return;
                        }

                        const matches = processId !== ''
                            && option.dataset.businessProcessId === processId
                            && (
                                functionId === ''
                                || option.dataset.businessFunctionId === functionId
                            );

                        option.hidden = !matches;
                        option.disabled = !matches;

                        if (option.selected && matches) {
                            selectedReferenceStillValid = true;
                        }
                    });

                    referenceSelect.disabled = processId === '';

                    if (!selectedReferenceStillValid) {
                        referenceSelect.value = '';
                    }
                };

                const syncDocumentNumberSuggestion = (form) => {
                    const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                    const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                    const suffixInput = form.querySelector('input[name="nomor_dokumen_suffix"]');

                    if (!processSelect || !functionSelect || !suffixInput) {
                        return;
                    }

                    if (suffixInput.dataset.userEdited === 'true') {
                        return;
                    }

                    const processId = processSelect.value;
                    const functionId = functionSelect.value;

                    if (processId === '' || functionId === '') {
                        return;
                    }

                    suffixInput.value = documentNumberSuggestions[`${processId}-${functionId}`] ?? '01';
                };

                document.querySelectorAll('form').forEach((form) => {
                    syncProcedureReferenceOptions(form);
                    syncDocumentNumberSuggestion(form);
                });

                document.addEventListener('input', (event) => {
                    const suffixInput = event.target.closest('input[name="nomor_dokumen_suffix"]');

                    if (suffixInput) {
                        suffixInput.dataset.userEdited = 'true';
                    }
                });

                document.addEventListener('change', (event) => {
                    if (!event.target.closest('select[name="m_proses_bisnis_id"], select[name="m_proses_fungsi_id"]')) {
                        return;
                    }

                    const form = event.target.closest('form');

                    if (form) {
                        syncProcedureReferenceOptions(form);
                        syncDocumentNumberSuggestion(form);
                    }
                });
            })();
        </script>
    @endonce

    @once
        <script>
            (() => {
                const syncOfficialPreparer = (root, user, sourceLabel) => {
                    const input = root.querySelector('[data-official-preparer-picker] [data-user-search-value]');
                    const card = root.querySelector('[data-official-preparer-card]');
                    const empty = root.querySelector('[data-official-preparer-empty]');
                    const initials = root.querySelector('[data-official-preparer-initials]');
                    const name = root.querySelector('[data-official-preparer-name]');
                    const meta = root.querySelector('[data-official-preparer-meta]');
                    const source = root.querySelector('[data-official-preparer-source]');

                    if (!input || !card || !empty || !initials || !name || !meta || !source) {
                        return;
                    }

                    input.value = user.id || '';
                    initials.textContent = user.initials || '-';
                    name.textContent = user.name || '-';
                    meta.textContent = user.title || user.email || '-';
                    source.textContent = sourceLabel;
                    card.classList.remove('hidden');
                    empty.classList.add('hidden');
                };

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-use-current-user]');

                    if (!button) {
                        return;
                    }

                    const root = button.closest('[data-official-preparer]');
                    const picker = root?.querySelector('[data-official-preparer-picker]');

                    window.setUserSearchSelect?.(picker, {
                        id: button.dataset.userId,
                        name: button.dataset.userName,
                        email: button.dataset.userEmail,
                        title: button.dataset.userTitle,
                        meta: button.dataset.userTitle || button.dataset.userEmail,
                        initials: button.dataset.userInitials,
                    });

                    syncOfficialPreparer(root, {
                        id: button.dataset.userId,
                        name: button.dataset.userName,
                        email: button.dataset.userEmail,
                        title: button.dataset.userTitle,
                        initials: button.dataset.userInitials,
                    }, 'Tanpa perwakilan');
                });

                document.addEventListener('user-search-select:selected', (event) => {
                    const picker = event.target.closest('[data-official-preparer-picker]');

                    if (!picker) {
                        return;
                    }

                    const root = picker.closest('[data-official-preparer]');
                    const user = event.detail;

                    syncOfficialPreparer(root, {
                        id: user.value,
                        name: user.name,
                        email: user.email,
                        title: user.title,
                        initials: user.initials,
                    }, 'Diwakilkan');
                });
            })();
        </script>
    @endonce
</x-layouts::app>
