<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentType;
use App\Models\ImportedExistingDocument;
use App\Models\ImportedExistingDocumentFile;
use App\Models\ImportedExistingDocumentRelation;
use App\Models\StatusDocument;
use App\Support\DocumentFiles\DocumentFileNumbering;
use App\Support\FinalDocuments\AutoGenerateApprovalPreview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportedExistingDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'state' => (string) $request->query('state', ''),
            'rule' => (string) $request->query('rule', ''),
            'process' => (string) $request->query('process', ''),
        ];

        $query = ImportedExistingDocument::query()
            ->with(['documentLevel', 'documentType', 'businessProcess', 'businessFunction', 'uploader'])
            ->withCount(['files', 'outgoingRelations', 'incomingImportedRelations']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_revisi', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"));
            });
        }

        if ($filters['rule'] !== '') {
            $query->where('obsolete_rule_type', $filters['rule']);
        }

        if ($filters['state'] !== '') {
            $query->where('document_state', $filters['state']);
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        return view('document-management.existing.imports.index', [
            'documents' => $query
                ->orderBy('document_state')
                ->orderByDesc('tanggal_obsolete')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'filters' => $filters,
            'stateOptions' => $this->stateOptions(),
            'ruleOptions' => $this->ruleOptions(),
            'processOptions' => ['' => 'Semua Proses'] + BusinessProcess::query()
                ->orderBy('nama_proses_bisnis')
                ->pluck('nama_proses_bisnis', 'id')
                ->all(),
            'canCreateImportedExisting' => $request->user()?->hasPermission('documents.obsolete.imports.create') ?? false,
        ]);
    }

    public function createMaster(): View
    {
        $documentLevels = collect(config('document-levels', []))
            ->except('level-4')
            ->all();

        return view('document-management.master.imports.index', [
            'documentLevels' => $documentLevels,
        ]);
    }

    public function createMasterLevel(string $level): View
    {
        return $this->createCurrentRuleImportLevel($level, ImportedExistingDocument::STATE_MASTER);
    }

    public function storeMasterLevel(Request $request, string $level): RedirectResponse
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->firstOrFail();

        $documentType = $this->documentTypeForLevel($level);

        $request->merge([
            'document_state' => ImportedExistingDocument::STATE_MASTER,
            'obsolete_rule_type' => ImportedExistingDocument::CURRENT_RULE,
            'm_document_level_id' => $documentLevelRecord->id,
            'm_document_types_id' => $documentType?->id,
        ]);

        return $this->storeForState($request);
    }

    public function createObsolete(): View
    {
        $documentLevels = collect(config('document-levels', []))
            ->except('level-4')
            ->all();

        return view('document-management.obsolete.imports.index', [
            'documentLevels' => $documentLevels,
        ]);
    }

    public function createObsoleteLegacy(): View
    {
        return $this->createForState(ImportedExistingDocument::STATE_OBSOLETE, true);
    }

    public function createObsoleteLevel(string $level): View
    {
        return $this->createCurrentRuleImportLevel($level, ImportedExistingDocument::STATE_OBSOLETE);
    }

    private function createForState(string $documentState, bool $legacyOnly = false): View
    {
        return view('document-management.existing.imports.create', [
            'documentState' => $documentState,
            'formAction' => route('documents.obsolete.imports.store'),
            'cancelUrl' => $legacyOnly ? route('documents.obsolete.imports.create') : route('documents.existing.imports.index'),
            'legacyOnly' => $legacyOnly,
            'ruleOptions' => $this->ruleOptions(),
            'documentLevelOptions' => ['' => 'Tidak dipetakan'] + DocumentLevel::query()->orderBy('id')->pluck('nama_dokumen', 'id')->all(),
            'documentTypeOptions' => ['' => 'Tidak dipetakan'] + DocumentType::query()->orderBy('nama_types')->pluck('nama_types', 'id')->all(),
            'processOptions' => ['' => 'Tidak dipetakan'] + BusinessProcess::query()->orderBy('nama_proses_bisnis')->pluck('nama_proses_bisnis', 'id')->all(),
            'functionOptions' => ['' => 'Tidak dipetakan'] + BusinessFunction::query()->orderBy('nama_proses_fungsi')->pluck('nama_proses_fungsi', 'id')->all(),
            'importedDocumentOptions' => ImportedExistingDocument::query()->orderBy('nama_dokumen')->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'existingDocumentOptions' => Document::query()->orderBy('nama_dokumen')->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'relationDocumentOptions' => $this->relationDocumentOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function storeNumberingSetup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope_identifier' => ['required', 'string', 'max:255'],
            'existing_start_number' => ['required', 'integer', 'min:0'],
            'existing_end_number' => ['required', 'integer', 'min:0', 'gte:existing_start_number'],
            'v2_start_number' => ['required', 'integer', 'min:0', 'gt:existing_end_number'],
        ]);

        DocumentNumberingSetup::updateOrCreate(
            ['scope_identifier' => $validated['scope_identifier']],
            [
                'existing_start_number' => $validated['existing_start_number'],
                'existing_end_number' => $validated['existing_end_number'],
                'v2_start_number' => $validated['v2_start_number'],
                'configured_by' => $request->user()->id,
                'configured_at' => now(),
            ],
        );

        return back()->with('status', 'Setup numbering dokumen existing berhasil disimpan.');
    }

    public function storeMaster(Request $request): RedirectResponse
    {

        $request->merge([
            'document_state' => ImportedExistingDocument::STATE_MASTER,
            'obsolete_rule_type' => ImportedExistingDocument::CURRENT_RULE,
        ]);

        return $this->storeForState($request);
    }

    public function storeObsolete(Request $request): RedirectResponse
    {
        $request->merge([
            'document_state' => ImportedExistingDocument::STATE_OBSOLETE,
            'obsolete_rule_type' => $request->input('obsolete_rule_type', ImportedExistingDocument::LEGACY_RULE),
        ]);

        return $this->storeForState($request);
    }

    public function storeObsoleteLevel(Request $request, string $level): RedirectResponse
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->firstOrFail();
        $documentType = $this->documentTypeForLevel($level);

        $request->merge([
            'document_state' => ImportedExistingDocument::STATE_OBSOLETE,
            'obsolete_rule_type' => ImportedExistingDocument::CURRENT_RULE,
            'm_document_level_id' => $documentLevelRecord->id,
            'm_document_types_id' => $documentType?->id,
        ]);

        return $this->storeForState($request);
    }

    private function createCurrentRuleImportLevel(string $level, string $documentState): View
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->first();

        $businessProcesses = BusinessProcess::query()->active()->orderBy('nama_proses_bisnis')->get();
        $businessFunctions = BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get();
        $departments = Department::query()->active()->orderBy('nama_department')->get();

        $workflowProcedures = collect();
        $importedProcedures = collect();
        if ($level === 'level-3') {
            $procedureLevelId = DocumentLevel::query()->where('kode', 'level-2')->value('id');
            $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
            if ($procedureLevelId) {
                if ($approvedStatusId) {
                    $workflowProcedures = Document::query()
                        ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                        ->where('m_document_level_id', $procedureLevelId)
                        ->where('m_status_document_id', $approvedStatusId)
                        ->orderBy('nomor_dokumen')
                        ->get();
                }
                $importedProcedures = ImportedExistingDocument::query()
                    ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                    ->where('m_document_level_id', $procedureLevelId)
                    ->where('document_state', ImportedExistingDocument::STATE_MASTER)
                    ->orderBy('nomor_dokumen')
                    ->get();
            }
        }

        $processOptions = ['' => 'Pilih Proses Bisnis'] + $businessProcesses->pluck('nama_proses_bisnis', 'id')->all();
        $functionOptions = ['' => 'Pilih Proses Fungsi'] + $businessFunctions->pluck('nama_proses_fungsi', 'id')->all();
        $departmentOptions = $departments->map(fn ($d) => [
            'value' => $d->id,
            'label' => ($d->kode_department ? $d->kode_department.' - ' : '').$d->nama_department,
        ])->values();
        $selectedDepartmentIds = collect(old('department_ids', []))
            ->map(fn ($id): string => (string) $id)
            ->all();

        return view('document-management.master.imports.create', [
            'level' => $level,
            'levelConfig' => $levelConfig,
            'documentLevelRecord' => $documentLevelRecord,
            'documentState' => $documentState,
            'processOptions' => $processOptions,
            'functionOptions' => $functionOptions,
            'departmentOptions' => $departmentOptions,
            'selectedDepartmentIds' => $selectedDepartmentIds,
            'workflowProcedures' => $workflowProcedures,
            'importedProcedures' => $importedProcedures,
            'importedDocumentOptions' => ImportedExistingDocument::query()->orderBy('nama_dokumen')->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'existingDocumentOptions' => Document::query()->orderBy('nama_dokumen')->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'relationDocumentOptions' => $this->relationDocumentOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    private function documentTypeForLevel(string $level): ?DocumentType
    {
        $typeNames = [
            'level-1' => ['Manual'],
            'level-2' => ['Prosedur'],
            'level-3' => ['IK', 'Instruksi Kerja'],
        ][$level] ?? ['IK', 'Instruksi Kerja'];

        return DocumentType::query()
            ->whereIn('nama_types', $typeNames)
            ->first();
    }

    private function storeForState(Request $request): RedirectResponse
    {
        $validated = $this->validateStoreRequest($request);
        $document = null;

        DB::transaction(function () use ($request, $validated, &$document): void {
            $document = ImportedExistingDocument::create([
                'document_state' => $validated['document_state'],
                'obsolete_rule_type' => $validated['obsolete_rule_type'],
                'm_document_level_id' => $validated['m_document_level_id'] ?? null,
                'm_document_types_id' => $validated['m_document_types_id'] ?? null,
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'] ?? null,
                'm_proses_fungsi_id' => $validated['m_proses_fungsi_id'] ?? null,
                'uploaded_by' => $request->user()->id,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $validated['nomor_dokumen'] ?? null,
                'nomor_revisi' => $validated['nomor_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'tanggal_obsolete' => $validated['tanggal_obsolete'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $document->departments()->sync($validated['department_ids'] ?? []);

            $this->validateRelationsAgainstSavedDocument($document, $validated['relations'] ?? []);

            $this->claimImportedExistingNumber($document, $request->user()->id);

            $this->storeImportedExistingFile(
                $document,
                $request->file('existing_document') ?: $request->file('obsolete_document'),
                $document->document_state === ImportedExistingDocument::STATE_OBSOLETE
                    ? ImportedExistingDocumentFile::OBSOLETE_DOCUMENT
                    : ImportedExistingDocumentFile::EXISTING_DOCUMENT,
                $request->user()->id,
            );

            if ($document->document_state === ImportedExistingDocument::STATE_OBSOLETE) {
                foreach ($request->file('attachments', []) as $attachment) {
                    $this->storeImportedExistingFile(
                        $document,
                        $attachment,
                        ImportedExistingDocumentFile::ATTACHMENT,
                        $request->user()->id,
                    );
                }
            }

            if ($replacementRelation = $this->replacementRelationAttributes($validated['replacement_reference'] ?? null)) {
                $document->outgoingRelations()->create([
                    'related_imported_existing_document_id' => $replacementRelation['related_imported_existing_document_id'],
                    'related_document_id' => $replacementRelation['related_document_id'],
                    'relation_type' => ImportedExistingDocumentRelation::SUPERSEDED_BY,
                    'keterangan' => 'Digantikan oleh dokumen terkait.',
                    'created_by' => $request->user()->id,
                ]);
            }

            foreach ($validated['relations'] ?? [] as $relation) {
                $document->outgoingRelations()->create([
                    'related_imported_existing_document_id' => $relation['related_imported_existing_document_id'] ?? null,
                    'related_document_id' => $relation['related_document_id'] ?? null,
                    'relation_type' => $relation['relation_type'],
                    'keterangan' => $relation['keterangan'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }

            if (filled($validated['reference'] ?? null)) {
                $refParts = explode('-', $validated['reference']);
                if (count($refParts) === 2) {
                    $refType = $refParts[0];
                    $refId = (int) $refParts[1];

                    $document->outgoingRelations()->create([
                        'related_imported_existing_document_id' => $refType === 'imported' ? $refId : null,
                        'related_document_id' => $refType === 'existing' ? $refId : null,
                        'relation_type' => ImportedExistingDocumentRelation::REFERENCES,
                        'keterangan' => 'Dokumen Acuan Prosedur',
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        if ($document->document_state === ImportedExistingDocument::STATE_MASTER) {
            return redirect()
                ->route('documents.master.imported.show', $document)
                ->with('status', 'Dokumen master existing berhasil diimport.');
        }

        return redirect()
            ->route('documents.existing.imports.show', $document)
            ->with('status', 'Arsip dokumen obsolete berhasil disimpan.');
    }

    public function show(ImportedExistingDocument $importedExistingDocument): View
    {
        $importedExistingDocument->load([
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'uploader',
            'files.uploader',
            'outgoingRelations.relatedImportedDocument',
            'outgoingRelations.relatedDocument.status',
            'outgoingRelations.creator',
            'incomingImportedRelations.sourceDocument',
            'incomingImportedRelations.creator',
        ]);

        return view('document-management.existing.imports.show', [
            'document' => $importedExistingDocument,
            'ruleOptions' => $this->ruleOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function storeRevision(Request $request, ImportedExistingDocument $importedExistingDocument): RedirectResponse
    {
        $importedExistingDocument->loadMissing('documentLevel', 'documentType', 'businessProcess', 'businessFunction', 'departments', 'files');

        abort_unless($importedExistingDocument->document_state === ImportedExistingDocument::STATE_MASTER, 404);

        $validated = $request->validate([
            'nama_dokumen' => ['nullable', 'string', 'max:255'],
            'official_preparer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'catatan_revisi' => ['nullable', 'string', 'max:1000'],
            'tanggal_terbit' => ['nullable', 'date'],
            'revision_content' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'revision_form' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'attachment_titles' => ['nullable', 'array', 'max:10'],
            'attachment_titles.*' => ['required_with:attachments.*', 'string', 'max:255'],
            'attachment_orders' => ['nullable', 'array', 'max:10'],
            'attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        $document = null;

        DB::transaction(function () use ($request, $validated, $importedExistingDocument, &$document): void {
            $status = StatusDocument::findByName(StatusDocument::PROPOSED);
            $nextRevision = $this->nextImportedExistingRevisionNumber($importedExistingDocument);
            $revisionFormNumber = $this->revisionFormNumberForImportedExisting($importedExistingDocument);

            $relation = $importedExistingDocument->outgoingRelations()
                ->where('relation_type', ImportedExistingDocumentRelation::REFERENCES)
                ->first();

            $referenceId = null;
            if ($relation) {
                if ($relation->related_document_id) {
                    $referenceId = $relation->related_document_id;
                } elseif ($relation->related_imported_existing_document_id) {
                    $referenceId = Document::query()
                        ->where('imported_existing_source_id', $relation->related_imported_existing_document_id)
                        ->whereHas('status', fn ($q) => $q->where('nama_status', StatusDocument::APPROVED))
                        ->value('id');
                }
            }

            $document = Document::create([
                'm_document_level_id' => $importedExistingDocument->m_document_level_id,
                'm_status_document_id' => $status->id,
                'm_document_types_id' => $importedExistingDocument->m_document_types_id,
                'm_proses_bisnis_id' => $importedExistingDocument->m_proses_bisnis_id,
                'm_proses_fungsi_id' => $importedExistingDocument->m_proses_fungsi_id,
                'user_id' => $request->user()->id,
                'official_preparer_id' => $validated['official_preparer_id'],
                'reference' => $referenceId,
                'revised_from' => null,
                'imported_existing_source_id' => $importedExistingDocument->id,
                'request_type' => 'revision',
                'nama_dokumen' => $validated['nama_dokumen'] ?? $importedExistingDocument->nama_dokumen,
                'nomor_dokumen' => $importedExistingDocument->nomor_dokumen,
                'nomor_lembar_revisi' => $revisionFormNumber,
                'nomor_revisi' => $nextRevision,
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'submitted_at' => now(),
                'created_at' => now(),
            ]);
            $document->departments()->sync($importedExistingDocument->departments->pluck('id')->all());
            $document->snapshotOfficialPreparer();

            $this->storeTDocumentFile($document, $request->file('revision_content'), 'revision_content', $request->user()->id);
            $this->storeTDocumentFile($document, $request->file('revision_form'), 'revision_form', $request->user()->id);
            $this->storeTDocumentAttachments($request, $document);
            app(DocumentFileNumbering::class)->assignMissingNumbers($document);

            $documentId = $document->id;
            $generatedById = $request->user()->id;

            DB::afterCommit(fn () => app(AutoGenerateApprovalPreview::class)
                ->generateIfNeeded($documentId, $generatedById));
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('status', 'Pengajuan revisi dari imported existing master berhasil dibuat.');
    }

    public function file(
        ImportedExistingDocument $importedExistingDocument,
        ImportedExistingDocumentFile $file,
    ): BinaryFileResponse {
        $this->authorizeImportedExistingFileAccess($importedExistingDocument, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function preview(
        ImportedExistingDocument $importedExistingDocument,
        ImportedExistingDocumentFile $file,
    ): BinaryFileResponse {
        $this->authorizeImportedExistingFileAccess($importedExistingDocument, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        $request->merge([
            'document_state' => $request->input('document_state', ImportedExistingDocument::STATE_OBSOLETE),
        ]);

        $validated = $request->validate([
            'document_state' => ['required', Rule::in(ImportedExistingDocument::DOCUMENT_STATES)],
            'obsolete_rule_type' => ['required', Rule::in(ImportedExistingDocument::RULE_TYPES)],
            'm_document_level_id' => ['required_if:obsolete_rule_type,'.ImportedExistingDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_levels', 'id')],
            'm_document_types_id' => ['required_if:obsolete_rule_type,'.ImportedExistingDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_types', 'id')],
            'm_proses_bisnis_id' => ['required_if:obsolete_rule_type,'.ImportedExistingDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['required_if:obsolete_rule_type,'.ImportedExistingDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'nomor_revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_terbit' => ['nullable', 'date'],
            'tanggal_obsolete' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'existing_document' => ['required_without:obsolete_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'obsolete_document' => ['required_without:existing_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'replacement_reference' => ['nullable', 'string', 'max:255'],
            'relations' => ['nullable', 'array', 'max:20'],
            'relations.*.relation_reference' => ['nullable', 'string', 'max:255'],
            'relations.*.related_imported_existing_document_id' => ['nullable', 'integer', Rule::exists('imported_existing_documents', 'id')],
            'relations.*.related_document_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'relations.*.relation_type' => ['required_with:relations', Rule::in(ImportedExistingDocumentRelation::RELATION_TYPES)],
            'relations.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($validated['obsolete_rule_type'] ?? null) === ImportedExistingDocument::CURRENT_RULE) {
            $validated['obsolete_rule_type'] = ImportedExistingDocument::CURRENT_RULE;
            $requiredMasterFields = [
                'm_document_level_id',
                'm_document_types_id',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
                'nomor_dokumen',
                'nomor_revisi',
                'department_ids',
            ];
            $masterErrors = [];

            foreach ($requiredMasterFields as $field) {
                if ($field === 'department_ids') {
                    if (count($validated['department_ids'] ?? []) === 0) {
                        $masterErrors[$field] = 'Pilih minimal satu department untuk import sesuai ketentuan saat ini.';
                    }

                    continue;
                }

                if (! filled($validated[$field] ?? null)) {
                    $masterErrors[$field] = 'Field ini wajib diisi untuk import sesuai ketentuan saat ini.';
                }
            }

            if (
                filled($validated['nomor_revisi'] ?? null)
                && ! preg_match('/^\d{2}\.\d{2}$/', (string) $validated['nomor_revisi'])
            ) {
                $masterErrors['nomor_revisi'] = 'Nomor revisi untuk import sesuai ketentuan saat ini wajib menggunakan format 00.00.';
            }

            if ($masterErrors !== []) {
                throw ValidationException::withMessages($masterErrors);
            }
        }

        if (
            filled($validated['replacement_reference'] ?? null)
            && $this->replacementRelationAttributes($validated['replacement_reference']) === null
        ) {
            throw ValidationException::withMessages([
                'replacement_reference' => 'Pilih dokumen pengganti yang valid.',
            ]);
        }

        $relationErrors = [];

        foreach ($validated['relations'] ?? [] as $index => $relation) {
            if (filled($relation['relation_reference'] ?? null)) {
                $relationAttributes = $this->relationTargetAttributes(
                    $relation['relation_reference'],
                    $relation['relation_type'] ?? null,
                );

                if ($relationAttributes === null) {
                    $relationErrors["relations.{$index}.relation_reference"] = 'Pilih target dokumen yang valid.';

                    continue;
                }

                $validated['relations'][$index]['related_imported_existing_document_id'] = $relationAttributes['related_imported_existing_document_id'];
                $validated['relations'][$index]['related_document_id'] = $relationAttributes['related_document_id'];
                $relation = $validated['relations'][$index];
            }

            $hasImportedTarget = filled($relation['related_imported_existing_document_id'] ?? null);
            $hasExistingDocumentTarget = filled($relation['related_document_id'] ?? null);

            if ($hasImportedTarget === $hasExistingDocumentTarget) {
                $relationErrors["relations.{$index}.related_imported_existing_document_id"] = 'Pilih tepat satu target relasi.';
            }
        }

        if ($relationErrors !== []) {
            throw ValidationException::withMessages($relationErrors);
        }

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $relations
     */
    private function validateRelationsAgainstSavedDocument(ImportedExistingDocument $document, array $relations): void
    {
        foreach ($relations as $index => $relation) {
            if ((int) ($relation['related_imported_existing_document_id'] ?? 0) === $document->id) {
                throw ValidationException::withMessages([
                    "relations.{$index}.related_imported_existing_document_id" => 'Dokumen tidak boleh berelasi ke dirinya sendiri.',
                ]);
            }
        }
    }

    private function storeImportedExistingFile(
        ImportedExistingDocument $document,
        mixed $file,
        string $type,
        int $uploadedBy,
    ): void {
        $path = $file->store("documents/imported-existing/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
        ]);
    }

    private function storeTDocumentAttachments(Request $request, Document $document): void
    {
        $titles = collect($request->input('attachment_titles', []))->values();
        $orders = collect($request->input('attachment_orders', []))->values();

        $attachments = collect(array_values($request->file('attachments', [])))
            ->map(function (mixed $attachment, int $index) use ($titles, $orders): array {
                return [
                    'file' => $attachment,
                    'title' => trim((string) ($titles->get($index) ?? '')),
                    'order' => max(1, (int) ($orders->get($index) ?: ($index + 1))),
                    'index' => $index,
                ];
            })
            ->sortBy([
                ['order', 'asc'],
                ['index', 'asc'],
            ]);

        foreach ($attachments as $attachmentData) {
            $attachment = $attachmentData['file'];
            $title = $attachmentData['title'];
            $order = $attachmentData['order'];

            $this->storeTDocumentFile(
                $document,
                $attachment,
                'attachment',
                $request->user()->id,
                $title !== '' ? $title : null,
                $order,
            );
        }
    }

    private function storeTDocumentFile(
        Document $document,
        mixed $file,
        string $type,
        int $uploadedBy,
        ?string $attachmentTitle = null,
        ?int $attachmentOrder = null,
    ): void {
        $path = $file->store("documents/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'document_number' => app(DocumentFileNumbering::class)->numberFor($document, $type, $attachmentOrder),
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
            'source_file_id' => $type === 'revision_form' ? $this->latestRevisionFormSourceFileId($document) : null,
            'attachment_title' => $attachmentTitle,
            'attachment_order' => $attachmentOrder,
        ]);
    }

    private function latestRevisionFormSourceFileId(Document $document): ?int
    {
        if ($document->request_type !== 'revision' || ! filled($document->nomor_dokumen)) {
            return null;
        }

        return DocumentFile::query()
            ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
            ->where('t_document.nomor_dokumen', $document->nomor_dokumen)
            ->where('t_document.id', '!=', $document->id)
            ->where('t_document_files.type_file', 'revision_form')
            ->orderByDesc('t_document.nomor_revisi')
            ->orderByDesc('t_document_files.id')
            ->value('t_document_files.id');
    }

    private function authorizeImportedExistingFileAccess(
        ImportedExistingDocument $document,
        ImportedExistingDocumentFile $file,
    ): void {
        abort_unless($file->imported_existing_document_id === $document->id, 404);
    }

    private function claimImportedExistingNumber(ImportedExistingDocument $document, int $userId): void
    {
        if (! filled($document->nomor_dokumen)) {
            return;
        }

        if (Document::query()->where('nomor_dokumen', $document->nomor_dokumen)->exists()) {
            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah digunakan oleh dokumen V2.',
            ]);
        }

        $existingRegistry = DocumentNumberRegistry::query()
            ->where('document_number', $document->nomor_dokumen)
            ->lockForUpdate()
            ->first();

        if ($existingRegistry !== null) {
            if ($document->document_state === ImportedExistingDocument::STATE_OBSOLETE
                && $existingRegistry->source_type === DocumentNumberRegistry::SOURCE_IMPORTED_EXISTING) {
                return;
            }

            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah terdaftar.',
            ]);
        }

        DocumentNumberRegistry::create([
            'document_number' => $document->nomor_dokumen,
            'scope_identifier' => $this->scopeIdentifierForNumber($document->nomor_dokumen),
            'source_type' => DocumentNumberRegistry::SOURCE_IMPORTED_EXISTING,
            'source_id' => $document->id,
            'registered_by' => $userId,
            'registered_at' => now(),
        ]);
    }

    private function scopeIdentifierForNumber(string $documentNumber): ?string
    {
        $segments = collect(explode('-', $documentNumber))
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        if ($segments->count() < 2) {
            return null;
        }

        $segments->pop();

        return $segments->implode('-');
    }

    private function nextImportedExistingRevisionNumber(ImportedExistingDocument $document): int
    {
        $baseRevision = $this->normalizeImportedExistingRevision($document->nomor_revisi);
        $latestWorkflowRevision = (int) Document::query()
            ->where('imported_existing_source_id', $document->id)
            ->max('nomor_revisi');

        return max($baseRevision, $latestWorkflowRevision) + 1;
    }

    private function normalizeImportedExistingRevision(?string $revision): int
    {
        if (! filled($revision)) {
            return 0;
        }

        $parts = explode('.', $revision, 2);
        $major = (int) preg_replace('/\D+/', '', $parts[0] ?? '0');
        $minor = (int) preg_replace('/\D+/', '', $parts[1] ?? '0');

        return ($major * 100) + $minor;
    }

    private function revisionFormNumberForImportedExisting(ImportedExistingDocument $source): ?string
    {
        $document = new Document([
            'm_document_level_id' => $source->m_document_level_id,
            'nomor_dokumen' => $source->nomor_dokumen,
        ]);
        $document->setRelation('documentLevel', $source->documentLevel);

        return app(DocumentFileNumbering::class)->revisionFormNumber($document);
    }

    /**
     * @return array<string, string>
     */
    private function stateOptions(): array
    {
        return [
            '' => 'Semua Status Existing',
            ImportedExistingDocument::STATE_MASTER => 'Existing Master',
            ImportedExistingDocument::STATE_OBSOLETE => 'Existing Obsolete',
        ];
    }

    private function relationDocumentOptions(): array
    {
        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        $workflowDocuments = Document::query()
            ->select(['id', 'nama_dokumen', 'nomor_dokumen', 'm_document_level_id', 'm_proses_bisnis_id', 'm_proses_fungsi_id', 'm_status_document_id', 'request_type'])
            ->orderBy('nama_dokumen')
            ->get();
        $importedDocuments = ImportedExistingDocument::query()
            ->select(['id', 'nama_dokumen', 'nomor_dokumen', 'document_state', 'm_document_level_id', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
            ->orderBy('nama_dokumen')
            ->get();

        return $workflowDocuments
            ->toBase()
            ->map(fn (Document $document): array => [
                'value' => 'existing-'.$document->id,
                'label' => $this->documentOptionLabel($document),
                'meta' => 'Dokumen V2',
                'is_master' => (bool) (
                    ($approvedStatusId === null || $document->m_status_document_id === $approvedStatusId)
                    && $document->request_type !== 'obsolete'
                ),
                'document_level_id' => $document->m_document_level_id,
                'business_process_id' => $document->m_proses_bisnis_id,
                'business_function_id' => $document->m_proses_fungsi_id,
            ])
            ->merge(
                $importedDocuments
                    ->toBase()
                    ->map(fn (ImportedExistingDocument $document): array => [
                        'value' => 'imported-'.$document->id,
                        'label' => $this->documentOptionLabel($document),
                        'meta' => $document->document_state === ImportedExistingDocument::STATE_MASTER
                            ? 'Imported Master'
                            : 'Arsip Obsolete',
                        'is_master' => $document->document_state === ImportedExistingDocument::STATE_MASTER,
                        'document_level_id' => $document->m_document_level_id,
                        'business_process_id' => $document->m_proses_bisnis_id,
                        'business_function_id' => $document->m_proses_fungsi_id,
                    ]),
            )
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function replacementRelationAttributes(?string $replacementReference): ?array
    {
        if (! filled($replacementReference)) {
            return null;
        }

        $parts = explode('-', $replacementReference, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$type, $id] = $parts;
        $id = (int) $id;

        if ($type === 'existing' && Document::query()->whereKey($id)->exists()) {
            return [
                'related_imported_existing_document_id' => null,
                'related_document_id' => $id,
            ];
        }

        if ($type === 'imported' && ImportedExistingDocument::query()->whereKey($id)->exists()) {
            return [
                'related_imported_existing_document_id' => $id,
                'related_document_id' => null,
            ];
        }

        return null;
    }

    private function relationTargetAttributes(?string $relationReference, ?string $relationType): ?array
    {
        if (! filled($relationReference)) {
            return null;
        }

        if ($relationType === ImportedExistingDocumentRelation::SUPERSEDED_BY) {
            return $this->replacementRelationAttributes($relationReference);
        }

        $parts = explode('-', $relationReference, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$type, $id] = $parts;
        $id = (int) $id;

        if ($type === 'existing' && Document::query()->whereKey($id)->exists()) {
            return [
                'related_imported_existing_document_id' => null,
                'related_document_id' => $id,
            ];
        }

        if ($type === 'imported' && ImportedExistingDocument::query()->whereKey($id)->exists()) {
            return [
                'related_imported_existing_document_id' => $id,
                'related_document_id' => null,
            ];
        }

        return null;
    }

    private function documentOptionLabel(Document|ImportedExistingDocument $document): string
    {
        return trim(($document->nomor_dokumen ? $document->nomor_dokumen.' - ' : '').$document->nama_dokumen);
    }

    /**
     * @return array<string, string>
     */
    private function ruleOptions(): array
    {
        return [
            '' => 'Semua Ketentuan',
            ImportedExistingDocument::CURRENT_RULE => 'Sesuai Ketentuan Saat Ini',
            ImportedExistingDocument::LEGACY_RULE => 'Mengikuti Ketentuan Dokumen Lama',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function relationTypeOptions(): array
    {
        return [
            ImportedExistingDocumentRelation::SUPERSEDED_BY => 'Digantikan Oleh',
            ImportedExistingDocumentRelation::REFERENCES => 'Referensi',
        ];
    }
}
