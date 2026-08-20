<x-layouts::app :title="__('Tambah Dokumen')">
    @php
        $revisionSource ??= null;
        $levelKey = request()->route('level');
        $level = config("document-levels.{$levelKey}");
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
            'level-4' => 'IV',
        ];
        $documentPrefixes = [
            'level-1' => 'SM',
            'level-2' => 'PS',
            'level-3' => 'IK',
            'level-4' => 'FM',
        ];
        $revisionPrefixes = [
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
        ];
        $revisionSourceLevelKey = $revisionSource?->documentLevel?->kode;
        $levelFourPrefix = match ($revisionSourceLevelKey) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $levelFourFormTitle = match ($revisionSourceLevelKey) {
            'level-1' => 'Form Manual',
            'level-2' => 'Form Prosedur',
            'level-3' => 'Form Instruksi Kerja',
            default => 'Form/Lembar Revisi',
        };

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
        $revisionDocumentSuffix = null;
        if ($revisionSource?->nomor_dokumen) {
            $sourceNumberSegments = collect(explode('-', $revisionSource->nomor_dokumen))
                ->filter()
                ->values();
            $revisionDocumentSuffix = $levelKey === 'level-4'
                ? $sourceNumberSegments->skip(1)->implode('-')
                : \Illuminate\Support\Str::afterLast($revisionSource->nomor_dokumen, '-');
        }
        $documentNumberPrefix = $revisionSource
            ? ($levelKey === 'level-4' ? $levelFourPrefix : ($revisionPrefixes[$levelKey] ?? 'FM'.$documentPrefixes[$levelKey]))
            : $documentPrefixes[$levelKey];
        $revisionRootDocumentId = $revisionSource?->revised_from ?: $revisionSource?->id;
        $latestRevisionNumber = $revisionRootDocumentId
            ? (int) \App\Models\Document::query()
                ->where(fn ($query) => $query
                    ->whereKey($revisionRootDocumentId)
                    ->orWhere('revised_from', $revisionRootDocumentId))
                ->max('nomor_revisi')
            : null;
        $selectedBusinessProcessId = old('m_proses_bisnis_id', $revisionSource?->m_proses_bisnis_id);
        $selectedBusinessFunctionId = old('m_proses_fungsi_id', $revisionSource?->m_proses_fungsi_id);
        $selectedReferenceId = old('reference', $revisionSource?->reference);
        $selectedDepartmentIds = old('department_ids', collect($revisionSource?->departments ?? [])->pluck('id')->all());
        $nextRevisionValue = $revisionSource
            ? '00.'.str_pad((string) (($latestRevisionNumber ?? $revisionSource->nomor_revisi) + 1), 2, '0', STR_PAD_LEFT)
            : '00.00';
        $selectedBusinessProcess = $businessProcesses->firstWhere('id', (int) $selectedBusinessProcessId);
        $documentNumberProcessCode = $selectedBusinessProcess?->kode ?: 'SMR';
        $documentNumberSegments = match ($levelKey) {
            'level-2' => [['value' => $documentNumberProcessCode, 'target' => 'business-process']],
            'level-3' => ['XXX', 'YY'],
            default => [],
        };
        $assignableUsers = \App\Models\User::query()
            ->with('department')
            ->whereKeyNot(auth()->id())
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

        <div>
            <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                {{ $levelKey === 'level-4' && $revisionSource ? 'Dokumen Level IV: '.$levelFourFormTitle : (($revisionSource ? 'Ajukan Revisi' : ($levelKey === 'level-1' ? 'Import' : 'Tambah')).' Dokumen Level '.$levelNumbers[$levelKey].' : '.$documentTitle) }}
            </h1>

            @if ($levelKey === 'level-4' && $revisionSource)
                <p class="mt-2 text-sm font-medium text-slate-500">Ajukan Revisi</p>
            @endif
        </div>

        @if ($revisionSource)
            <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-800">
                Revisi dari {{ $revisionSource->nomor_dokumen ?: '-' }} - {{ $revisionSource->nama_dokumen }}.
            </div>
        @endif

        @if ($levelKey === 'level-1')
            <form method="POST" action="{{ route('documents.store', $levelKey) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                @csrf
                @if ($revisionSource)
                    <input type="hidden" name="revised_from" value="{{ $revisionSource->id }}">
                @endif

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
                                    value="{{ old('nama_dokumen', $revisionSource?->nama_dokumen) }}"
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
                                <input type="text" value="{{ $documentNumberPrefix }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                <span class="text-lg font-semibold text-slate-500">-</span>
                                <input type="text" name="nomor_dokumen_suffix" value="{{ old('nomor_dokumen_suffix', $revisionDocumentSuffix) }}" required class="h-14 w-full rounded-lg border border-slate-300 bg-white px-3 text-center text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
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
                                value="{{ old('nomor_revisi', $revisionSource ? $nextRevisionValue : null) }}"
                                @readonly($revisionSource)
                                class="h-14 w-full rounded-lg border {{ $revisionSource ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-slate-300 bg-white text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' }} px-4 text-base font-semibold outline-none transition"
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
                @if ($revisionSource)
                    <input type="hidden" name="revised_from" value="{{ $revisionSource->id }}">
                @endif

                <div class="space-y-6">
                    <x-documents.form-section title="Informasi Dokumen">
                        @if ($revisionSource)
                            <input type="hidden" name="nama_dokumen" value="{{ $revisionSource->nama_dokumen }}">
                            <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">
                            <input type="hidden" name="m_proses_bisnis_id" value="{{ $revisionSource->m_proses_bisnis_id }}">
                            <input type="hidden" name="m_proses_fungsi_id" value="{{ $revisionSource->m_proses_fungsi_id }}">
                            @foreach ($selectedDepartmentIds as $departmentId)
                                <input type="hidden" name="department_ids[]" value="{{ $departmentId }}">
                            @endforeach
                            @if ($levelKey === 'level-3')
                                <input type="hidden" name="reference" value="{{ $revisionSource->reference }}">
                            @endif

                            <dl class="divide-y divide-slate-100 px-6 py-4">
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Nama Dokumen</dt>
                                    <dd class="text-sm font-bold uppercase leading-6 text-slate-900">{{ $revisionSource->nama_dokumen }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $levelDisplayValue ?: '-' }}</dd>
                                </div>
                                @if ($levelKey === 'level-4')
                                    <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                        <dt class="text-sm font-semibold text-slate-500">Dokumen Induk</dt>
                                        <dd class="text-sm font-bold text-slate-900">
                                            {{ $revisionSource->documentLevel?->nama_level ?: '-' }} : {{ \Illuminate\Support\Str::after($revisionSource->documentLevel?->nama_dokumen ?? '', ': ') }}
                                        </dd>
                                    </div>
                                @endif
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Nomor Dokumen</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $revisionSource->nomor_dokumen ?: '-' }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                                    <dd class="text-sm font-bold text-slate-900">
                                        {{ collect([$revisionSource->businessProcess?->nama_proses_bisnis, $revisionSource->businessFunction?->nama_proses_fungsi])->filter()->implode(' / ') ?: '-' }}
                                    </dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                                    <dd class="text-sm font-bold text-slate-900">
                                        {{ $revisionSource->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}
                                    </dd>
                                </div>
                                @if ($revisionSource->referenceDocument)
                                    <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                        <dt class="text-sm font-semibold text-slate-500">Dokumen Acuan</dt>
                                        <dd class="text-sm font-bold text-slate-900">
                                            {{ $revisionSource->referenceDocument->nomor_dokumen ?: '-' }} - {{ $revisionSource->referenceDocument->nama_dokumen }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        @else
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

                                <label @class(['block', 'md:col-span-2' => $levelKey !== 'level-3'])>
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

                                <x-ui.multi-select
                                    label="Department Terkait"
                                    name="department_ids"
                                    :options="$departmentOptions"
                                    selected-placeholder="Tambah Department"
                                    required
                                    class="md:col-span-1"
                                />

                                @if ($levelKey === 'level-3')
                                    <label class="block">
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
                            </div>
                        @endif
                    </x-documents.form-section>

                    <x-documents.official-preparer :label="$ownerLabel" :users="$assignableUsers" />

                    <x-documents.form-section :title="$levelKey === 'level-4' ? 'Dokumen Revisi' : 'Isi Dokumen'" icon="cloud-arrow-up">
                        <div class="space-y-6 px-6 py-6">
                            @if ($levelKey === 'level-4')
                                <x-documents.upload-toggle-card
                                    title="1. Isi Dokumen Versi Revisi"
                                    button-label="Upload Dokumen Revisi"
                                    tone="sky"
                                >
                                    <x-ui.file-upload
                                        label="Upload Isi Dokumen Versi Revisi"
                                        name="revision_content"
                                        accept=".pdf,application/pdf"
                                        hint="Upload dokumen utama yang sudah direvisi. Format PDF."
                                        :max-files="1"
                                        :max-file-size-kb="10240"
                                        :required="old('submit_action') === 'submit'"
                                    />

                                    @error('revision_content')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </x-documents.upload-toggle-card>

                                <x-documents.upload-toggle-card
                                    title="2. Lembar Revisi"
                                    button-label="Upload Lembar Revisi"
                                    tone="sky"
                                >
                                    <x-ui.file-upload
                                        label="Upload Lembar Revisi"
                                        name="revision_form"
                                        accept=".pdf,application/pdf"
                                        hint="Upload form/lembar revisi yang menjelaskan perubahan. Format PDF."
                                        :max-files="1"
                                        :max-file-size-kb="10240"
                                        :required="old('submit_action') === 'submit'"
                                    />

                                    @error('revision_form')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </x-documents.upload-toggle-card>
                            @else
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
                            @endif

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
                                :prefix="$documentNumberPrefix"
                                :segments="$documentNumberSegments"
                                :default-value="$revisionDocumentSuffix"
                            />

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                                <input
                                    type="text"
                                    value="{{ $nextRevisionValue }}"
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
