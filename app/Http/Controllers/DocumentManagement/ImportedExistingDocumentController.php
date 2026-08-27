<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentType;
use App\Models\ImportedExistingDocument;
use App\Models\ImportedExistingDocumentFile;
use App\Models\ImportedExistingDocumentRelation;
use App\Models\StatusDocument;
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
        return $this->createForState(ImportedExistingDocument::STATE_MASTER);
    }

    public function createObsolete(): View
    {
        return $this->createForState(ImportedExistingDocument::STATE_OBSOLETE);
    }

    private function createForState(string $documentState): View
    {
        return view('document-management.existing.imports.create', [
            'documentState' => $documentState,
            'formAction' => $documentState === ImportedExistingDocument::STATE_MASTER
                ? route('documents.master.imports.store')
                : route('documents.obsolete.imports.store'),
            'cancelUrl' => $documentState === ImportedExistingDocument::STATE_MASTER
                ? route('documents.master')
                : route('documents.existing.imports.index'),
            'ruleOptions' => $this->ruleOptions(),
            'documentLevelOptions' => ['' => 'Tidak dipetakan'] + DocumentLevel::query()->orderBy('id')->pluck('nama_dokumen', 'id')->all(),
            'documentTypeOptions' => ['' => 'Tidak dipetakan'] + DocumentType::query()->orderBy('nama_types')->pluck('nama_types', 'id')->all(),
            'processOptions' => ['' => 'Tidak dipetakan'] + BusinessProcess::query()->orderBy('nama_proses_bisnis')->pluck('nama_proses_bisnis', 'id')->all(),
            'functionOptions' => ['' => 'Tidak dipetakan'] + BusinessFunction::query()->orderBy('nama_proses_fungsi')->pluck('nama_proses_fungsi', 'id')->all(),
            'importedDocumentOptions' => ImportedExistingDocument::query()
                ->orderBy('nama_dokumen')
                ->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'existingDocumentOptions' => Document::query()
                ->orderBy('nama_dokumen')
                ->get(['id', 'nama_dokumen', 'nomor_dokumen']),
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
        ]);

        return $this->storeForState($request);
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

            $this->validateRelationsAgainstSavedDocument($document, $validated['relations'] ?? []);

            $this->claimImportedExistingNumber($document, $request->user()->id);

            $this->storeImportedExistingFile(
                $document,
                $request->file('existing_document') ?: $request->file('obsolete_document'),
                ImportedExistingDocumentFile::EXISTING_DOCUMENT,
                $request->user()->id,
            );

            foreach ($request->file('attachments', []) as $attachment) {
                $this->storeImportedExistingFile(
                    $document,
                    $attachment,
                    ImportedExistingDocumentFile::ATTACHMENT,
                    $request->user()->id,
                );
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
        $importedExistingDocument->loadMissing('documentLevel', 'documentType', 'businessProcess', 'businessFunction', 'files');

        abort_unless($importedExistingDocument->document_state === ImportedExistingDocument::STATE_MASTER, 404);

        $validated = $request->validate([
            'nama_dokumen' => ['nullable', 'string', 'max:255'],
            'official_preparer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'catatan_revisi' => ['nullable', 'string', 'max:1000'],
            'tanggal_terbit' => ['nullable', 'date'],
            'revision_content' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'revision_form' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $document = null;

        DB::transaction(function () use ($request, $validated, $importedExistingDocument, &$document): void {
            $status = StatusDocument::findByName(StatusDocument::PROPOSED);

            $document = Document::create([
                'm_document_level_id' => $importedExistingDocument->m_document_level_id,
                'm_status_document_id' => $status->id,
                'm_document_types_id' => $importedExistingDocument->m_document_types_id,
                'm_proses_bisnis_id' => $importedExistingDocument->m_proses_bisnis_id,
                'm_proses_fungsi_id' => $importedExistingDocument->m_proses_fungsi_id,
                'user_id' => $request->user()->id,
                'official_preparer_id' => $validated['official_preparer_id'],
                'reference' => null,
                'revised_from' => null,
                'imported_existing_source_id' => $importedExistingDocument->id,
                'request_type' => 'revision',
                'nama_dokumen' => $validated['nama_dokumen'] ?? $importedExistingDocument->nama_dokumen,
                'nomor_dokumen' => $importedExistingDocument->nomor_dokumen,
                'nomor_revisi' => 1,
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'submitted_at' => now(),
                'created_at' => now(),
            ]);

            $this->storeTDocumentFile($document, $request->file('revision_content'), 'revision_content', $request->user()->id);
            $this->storeTDocumentFile($document, $request->file('revision_form'), 'revision_form', $request->user()->id);
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
            'existing_document' => ['required_without:obsolete_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'obsolete_document' => ['required_without:existing_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'relations' => ['nullable', 'array', 'max:20'],
            'relations.*.related_imported_existing_document_id' => ['nullable', 'integer', Rule::exists('imported_existing_documents', 'id')],
            'relations.*.related_document_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'relations.*.relation_type' => ['required_with:relations', Rule::in(ImportedExistingDocumentRelation::RELATION_TYPES)],
            'relations.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($validated['document_state'] ?? null) === ImportedExistingDocument::STATE_MASTER) {
            $validated['obsolete_rule_type'] = ImportedExistingDocument::CURRENT_RULE;
            $requiredMasterFields = [
                'm_document_level_id',
                'm_document_types_id',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
                'nomor_dokumen',
            ];
            $masterErrors = [];

            foreach ($requiredMasterFields as $field) {
                if (! filled($validated[$field] ?? null)) {
                    $masterErrors[$field] = 'Field ini wajib diisi untuk imported existing master.';
                }
            }

            if ($masterErrors !== []) {
                throw ValidationException::withMessages($masterErrors);
            }
        }

        $relationErrors = [];

        foreach ($validated['relations'] ?? [] as $index => $relation) {
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

    private function storeTDocumentFile(Document $document, mixed $file, string $type, int $uploadedBy): void
    {
        $path = $file->store("documents/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
        ]);
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
            ImportedExistingDocumentRelation::RELATED_TO => 'Berkaitan Dengan',
            ImportedExistingDocumentRelation::REFERENCES => 'Referensi',
        ];
    }
}
