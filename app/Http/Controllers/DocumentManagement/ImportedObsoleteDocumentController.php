<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\ImportedObsoleteDocument;
use App\Models\ImportedObsoleteDocumentFile;
use App\Models\ImportedObsoleteDocumentRelation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportedObsoleteDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'rule' => (string) $request->query('rule', ''),
            'process' => (string) $request->query('process', ''),
        ];

        $query = ImportedObsoleteDocument::query()
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

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        return view('document-management.obsolete.imports.index', [
            'documents' => $query
                ->orderByDesc('tanggal_obsolete')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'filters' => $filters,
            'ruleOptions' => $this->ruleOptions(),
            'processOptions' => ['' => 'Semua Proses'] + BusinessProcess::query()
                ->orderBy('nama_proses_bisnis')
                ->pluck('nama_proses_bisnis', 'id')
                ->all(),
            'canCreateImportedObsolete' => $request->user()?->hasPermission('documents.obsolete.imports.create') ?? false,
        ]);
    }

    public function create(): View
    {
        return view('document-management.obsolete.imports.create', [
            'ruleOptions' => $this->ruleOptions(),
            'documentLevelOptions' => ['' => 'Tidak dipetakan'] + DocumentLevel::query()->orderBy('id')->pluck('nama_dokumen', 'id')->all(),
            'documentTypeOptions' => ['' => 'Tidak dipetakan'] + DocumentType::query()->orderBy('nama_types')->pluck('nama_types', 'id')->all(),
            'processOptions' => ['' => 'Tidak dipetakan'] + BusinessProcess::query()->orderBy('nama_proses_bisnis')->pluck('nama_proses_bisnis', 'id')->all(),
            'functionOptions' => ['' => 'Tidak dipetakan'] + BusinessFunction::query()->orderBy('nama_proses_fungsi')->pluck('nama_proses_fungsi', 'id')->all(),
            'importedDocumentOptions' => ImportedObsoleteDocument::query()
                ->orderBy('nama_dokumen')
                ->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'existingDocumentOptions' => Document::query()
                ->orderBy('nama_dokumen')
                ->get(['id', 'nama_dokumen', 'nomor_dokumen']),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStoreRequest($request);
        $document = null;

        DB::transaction(function () use ($request, $validated, &$document): void {
            $document = ImportedObsoleteDocument::create([
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

            $this->storeImportedObsoleteFile(
                $document,
                $request->file('obsolete_document'),
                ImportedObsoleteDocumentFile::OBSOLETE_DOCUMENT,
                $request->user()->id,
            );

            foreach ($request->file('attachments', []) as $attachment) {
                $this->storeImportedObsoleteFile(
                    $document,
                    $attachment,
                    ImportedObsoleteDocumentFile::ATTACHMENT,
                    $request->user()->id,
                );
            }

            foreach ($validated['relations'] ?? [] as $relation) {
                $document->outgoingRelations()->create([
                    'related_imported_obsolete_document_id' => $relation['related_imported_obsolete_document_id'] ?? null,
                    'related_document_id' => $relation['related_document_id'] ?? null,
                    'relation_type' => $relation['relation_type'],
                    'keterangan' => $relation['keterangan'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()
            ->route('documents.obsolete.imports.show', $document)
            ->with('status', 'Arsip dokumen obsolete legacy berhasil disimpan.');
    }

    public function show(ImportedObsoleteDocument $importedObsoleteDocument): View
    {
        $importedObsoleteDocument->load([
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

        return view('document-management.obsolete.imports.show', [
            'document' => $importedObsoleteDocument,
            'ruleOptions' => $this->ruleOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function file(
        ImportedObsoleteDocument $importedObsoleteDocument,
        ImportedObsoleteDocumentFile $file,
    ): BinaryFileResponse {
        $this->authorizeImportedObsoleteFileAccess($importedObsoleteDocument, $file);

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
        ImportedObsoleteDocument $importedObsoleteDocument,
        ImportedObsoleteDocumentFile $file,
    ): BinaryFileResponse {
        $this->authorizeImportedObsoleteFileAccess($importedObsoleteDocument, $file);
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
        $validated = $request->validate([
            'obsolete_rule_type' => ['required', Rule::in(ImportedObsoleteDocument::RULE_TYPES)],
            'm_document_level_id' => ['required_if:obsolete_rule_type,'.ImportedObsoleteDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_levels', 'id')],
            'm_document_types_id' => ['required_if:obsolete_rule_type,'.ImportedObsoleteDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_types', 'id')],
            'm_proses_bisnis_id' => ['required_if:obsolete_rule_type,'.ImportedObsoleteDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['required_if:obsolete_rule_type,'.ImportedObsoleteDocument::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'nomor_revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_terbit' => ['nullable', 'date'],
            'tanggal_obsolete' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'obsolete_document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'relations' => ['nullable', 'array', 'max:20'],
            'relations.*.related_imported_obsolete_document_id' => ['nullable', 'integer', Rule::exists('imported_obsolete_documents', 'id')],
            'relations.*.related_document_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'relations.*.relation_type' => ['required_with:relations', Rule::in(ImportedObsoleteDocumentRelation::RELATION_TYPES)],
            'relations.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $relationErrors = [];

        foreach ($validated['relations'] ?? [] as $index => $relation) {
            $hasImportedTarget = filled($relation['related_imported_obsolete_document_id'] ?? null);
            $hasExistingDocumentTarget = filled($relation['related_document_id'] ?? null);

            if ($hasImportedTarget === $hasExistingDocumentTarget) {
                $relationErrors["relations.{$index}.related_imported_obsolete_document_id"] = 'Pilih tepat satu target relasi.';
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
    private function validateRelationsAgainstSavedDocument(ImportedObsoleteDocument $document, array $relations): void
    {
        foreach ($relations as $index => $relation) {
            if ((int) ($relation['related_imported_obsolete_document_id'] ?? 0) === $document->id) {
                throw ValidationException::withMessages([
                    "relations.{$index}.related_imported_obsolete_document_id" => 'Dokumen tidak boleh berelasi ke dirinya sendiri.',
                ]);
            }
        }
    }

    private function storeImportedObsoleteFile(
        ImportedObsoleteDocument $document,
        mixed $file,
        string $type,
        int $uploadedBy,
    ): void {
        $path = $file->store("documents/imported-obsolete/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
        ]);
    }

    private function authorizeImportedObsoleteFileAccess(
        ImportedObsoleteDocument $document,
        ImportedObsoleteDocumentFile $file,
    ): void {
        abort_unless($file->imported_obsolete_document_id === $document->id, 404);
    }

    /**
     * @return array<string, string>
     */
    private function ruleOptions(): array
    {
        return [
            '' => 'Semua Ketentuan',
            ImportedObsoleteDocument::CURRENT_RULE => 'Sesuai Ketentuan Saat Ini',
            ImportedObsoleteDocument::LEGACY_RULE => 'Mengikuti Ketentuan Dokumen Lama',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function relationTypeOptions(): array
    {
        return [
            ImportedObsoleteDocumentRelation::SUPERSEDED_BY => 'Digantikan Oleh',
            ImportedObsoleteDocumentRelation::RELATED_TO => 'Berkaitan Dengan',
            ImportedObsoleteDocumentRelation::REFERENCES => 'Referensi',
        ];
    }
}
