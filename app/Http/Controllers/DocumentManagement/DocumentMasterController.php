<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\ImportedExistingDocument;
use App\Models\ImportedExistingDocumentFile;
use App\Models\ImportedExistingDocumentRelation;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DocumentHistory;
use App\Support\FinalDocuments\DynamicFinalDocumentRenderer;
use App\Support\FinalDocuments\PdfDocumentContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentMasterController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'process' => (string) $request->query('process', ''),
            'stamp' => (string) $request->query('stamp', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        $query = Document::query()
            ->with([
                'status',
                'documentLevel',
                'documentType',
                'businessProcess',
                'businessFunction',
                'creator',
                'officialPreparer',
                'departments',
                'files',
                'revisedFrom.status',
                'revisedFrom.documentLevel',
                'revisedFrom.businessProcess',
                'revisedFrom.businessFunction',
                'revisedFrom.departments',
                'obsoleteRevisions.status',
                'obsoleteRevisions.documentLevel',
                'obsoleteRevisions.businessProcess',
                'obsoleteRevisions.businessFunction',
                'obsoleteRevisions.departments',
            ])
            ->where('m_status_document_id', $approvedStatusId)
            ->where(fn ($query) => $this->whereVisibleMasterRecord($query));

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"))
                    ->orWhereHas('departments', fn ($query) => $query->where('nama_department', 'like', "%{$search}%"));
            });
        }

        if ($filters['type'] !== '') {
            $query->where('m_document_level_id', $filters['type']);
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('approved_at')->orderBy('tanggal_terbit')->orderBy('id'),
            'name_asc' => $query->orderBy('nama_dokumen')->orderBy('nomor_dokumen'),
            'name_desc' => $query->orderByDesc('nama_dokumen')->orderByDesc('nomor_dokumen'),
            'revision_desc' => $query->orderByDesc('nomor_revisi')->orderByDesc('approved_at')->orderByDesc('id'),
            default => $query->orderByDesc('approved_at')->orderByDesc('tanggal_terbit')->orderByDesc('id'),
        };

        $workflowRows = $query->get()
            ->groupBy(fn (Document $document): int => $document->revisionRootId())
            ->map(fn ($family): Document => $family
                ->sortByDesc(fn (Document $document): string => sprintf(
                    '%010d-%010d-%010d',
                    $document->nomor_revisi,
                    $document->approved_at?->timestamp ?? 0,
                    $document->id,
                ))
                ->first())
            ->values()
            ->map(fn (Document $document) => $this->presentWorkflowMasterRow($request, $document));

        $importedRows = $this->importedMasterQuery($filters)
            ->get()
            ->map(fn (ImportedExistingDocument $document) => $this->presentImportedMasterRow($document));

        $documents = $this->sortPresentedMasterRows(
            collect($workflowRows->all())->merge($importedRows->all()),
            $filters['sort'],
        );

        $typeOptions = ['' => 'Semua Level'] + DocumentLevel::query()
            ->orderBy('id')
            ->pluck('nama_dokumen', 'id')
            ->all();

        $processOptions = ['' => 'Semua Proses'] + BusinessProcess::query()
            ->orderBy('nama_proses_bisnis')
            ->pluck('nama_proses_bisnis', 'id')
            ->all();

        return view('document-management.master.index', [
            'documents' => $documents,
            'totalDocuments' => $documents->count(),
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'stampOptions' => [
                '' => 'Semua Stamp',
                StatusDocument::APPROVED => 'Master',
            ],
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
                'revision_desc' => 'Revisi Tertinggi',
            ],
            'canImportMaster' => $request->user()?->hasPermission('documents.master.imports.create') ?? false,
        ]);
    }

    public function show(Request $request, Document $document): View
    {
        $document->load([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'creator',
            'officialPreparer',
            'departments',
            'files.uploader',
            'finalArtifacts',
            'approvals.status',
            'approvals.approver',
            'approvals.role',
            'documentLevel.approvalFlows.stages',
            'revisedFrom.status',
        ]);

        abort_unless(
            $document->status?->nama_status === StatusDocument::APPROVED,
            404,
        );
        abort_unless($this->isVisibleMasterRecord($document), 404);

        return view('document-management.master.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'revisionRequestDisplayNumber' => $this->revisionRequestDisplayNumber($document),
            'canRequestRevision' => $this->canRequestRevision($request, $document),
            'canRequestObsolete' => $this->canRequestObsolete($request, $document),
            'canRestoreMaster' => $this->canRestoreMaster($request, $document),
            'approvalFlowStages' => $document->documentLevel
                ?->approvalFlows
                ->flatMap(fn ($flow) => $flow->stages)
                ->sortBy('stage_order')
                ->values()
                ?? collect(),
            'contentFiles' => $document->files->whereIn('type_file', ['filled_template', 'imported_document', 'revision_content'])->values(),
            'attachmentFiles' => $document->files
                ->whereIn('type_file', ['attachment', 'revision_form'])
                ->sortBy(fn (DocumentFile $file): string => $file->type_file === 'revision_form'
                    ? sprintf('%010d-%010d-%010d', 0, 0, $file->id)
                    : $file->attachmentSortKey())
                ->values(),
            'generatedPrintout' => $this->latestGeneratedPrintout($document),
            'canPreviewGeneratedPrintout' => app(DynamicFinalDocumentRenderer::class)
                ->canRender($document, PdfDocumentContext::FINAL_DOCUMENT),
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
            'relatedObsoleteDocuments' => $this->relatedImportedObsoleteForWorkflowMaster($document),
        ]);
    }

    public function showImported(Request $request, ImportedExistingDocument $importedExistingDocument): View
    {
        $importedExistingDocument->load([
            'documentLevel.approvalFlows.stages',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'uploader',
            'files.uploader',
            'incomingImportedRelations.sourceDocument',
            'incomingImportedRelations.creator',
        ]);

        abort_unless($importedExistingDocument->document_state === ImportedExistingDocument::STATE_MASTER, 404);
        abort_unless($request->user()?->hasPermission('documents.master.detail'), 403);

        $importedExistingDocument->setRelation('approvals', collect());
        $importedExistingDocument->setAttribute('formatted_revision', $this->formatImportedRevision($importedExistingDocument));

        return view('document-management.master.imported-show', [
            'document' => $importedExistingDocument,
            'masterDisplayNumber' => $importedExistingDocument->nomor_dokumen ?: '-',
            'revisionRequestDisplayNumber' => null,
            'canRequestRevision' => $request->user()?->hasPermission('documents.existing.imports.revision') ?? false,
            'canRequestObsolete' => $this->canRequestImportedObsolete($request, $importedExistingDocument),
            'approvalFlowStages' => collect(),
            'contentFiles' => $importedExistingDocument->files
                ->where('type_file', ImportedExistingDocumentFile::EXISTING_DOCUMENT)
                ->values(),
            'attachmentFiles' => $importedExistingDocument->files
                ->where('type_file', ImportedExistingDocumentFile::ATTACHMENT)
                ->values(),
            'relatedObsoleteDocuments' => $this->relatedImportedObsoleteForImportedMaster($importedExistingDocument),
            'importNote' => $this->importedMasterNote($importedExistingDocument),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function obsoleteImported(Request $request, ImportedExistingDocument $importedExistingDocument): RedirectResponse
    {
        $importedExistingDocument->loadMissing('files');

        abort_unless($importedExistingDocument->document_state === ImportedExistingDocument::STATE_MASTER, 404);
        abort_unless($this->canRequestImportedObsolete($request, $importedExistingDocument), 403);

        $validated = $request->validate([
            'catatan_obsolete' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($importedExistingDocument, $validated): void {
            $importedExistingDocument->update([
                'document_state' => ImportedExistingDocument::STATE_OBSOLETE,
                'tanggal_obsolete' => now(),
                'catatan' => $this->appendImportedObsoleteNote(
                    $importedExistingDocument->catatan,
                    $validated['catatan_obsolete'],
                ),
            ]);

            $importedExistingDocument->files()
                ->where('type_file', ImportedExistingDocumentFile::EXISTING_DOCUMENT)
                ->update(['type_file' => ImportedExistingDocumentFile::OBSOLETE_DOCUMENT]);
        });

        return redirect()
            ->route('documents.existing.imports.show', $importedExistingDocument)
            ->with('status', 'Imported existing master berhasil diobsolete.');
    }

    public function obsolete(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status', 'documentLevel', 'departments');

        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->isVisibleMasterRecord($document), 404);
        abort_unless($this->canRequestObsolete($request, $document), 403);

        $validated = $request->validate([
            'catatan_obsolete' => ['required', 'string', 'max:1000'],
        ]);

        $status = StatusDocument::findByName(StatusDocument::PROPOSED);

        $requestDocument = Document::create([
            'm_document_level_id' => $document->m_document_level_id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $document->m_document_types_id,
            'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
            'user_id' => $request->user()->id,
            'official_preparer_id' => $document->official_preparer_id ?: $request->user()->id,
            'reference' => null,
            'revised_from' => $document->id,
            'request_type' => 'obsolete',
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $this->masterDisplayNumber($document),
            'nomor_revisi' => $document->nomor_revisi,
            'catatan_revisi' => $validated['catatan_obsolete'],
            'submitted_at' => now(),
        ]);
        $requestDocument->departments()->sync($document->departments()->pluck('departments.id')->all());

        return redirect()
            ->route('documents.inbox')
            ->with('status', 'Pengajuan obsolete berhasil dikirim.');
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status', 'departments');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($this->canRestoreMaster($request, $document), 403);

        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);
        $family = $document->revisionFamily();
        $familyIds = $family->pluck('id');
        $activeMaster = $family
            ->first(fn (Document $revision): bool => $revision->id !== $document->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $this->isVisibleMasterRecord($revision));

        if ($activeMaster !== null) {
            return redirect()
                ->route('documents.master.show', $document)
                ->with('restore_warning', [
                    'title' => 'Belum Bisa Dijadikan Master',
                    'message' => $this->restoreBlockedMessage($document, $activeMaster),
                ]);
        }

        DB::transaction(function () use ($document, $familyIds, $approvedStatus, $obsoleteStatus): void {
            $restoredAt = now();

            Document::query()
                ->whereIn('id', $familyIds)
                ->where('id', '!=', $document->id)
                ->where('m_status_document_id', $approvedStatus->id)
                ->where(fn ($query) => $this->whereVisibleMasterRecord($query))
                ->update([
                    'm_status_document_id' => $obsoleteStatus->id,
                    'obsolete_at' => $restoredAt,
                ]);

            $document->update([
                'm_status_document_id' => $approvedStatus->id,
                'approved_at' => $restoredAt,
                'obsolete_at' => null,
            ]);
        });

        return redirect()
            ->route('documents.master.show', $document)
            ->with('status', 'Dokumen berhasil dijadikan master.');
    }

    public function file(Request $request, Document $document, DocumentFile $file, RecordDocumentDownload $recordDocumentDownload): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file, [
            'name' => $document->nama_dokumen,
            'number' => $this->masterDisplayNumber($document),
            'revision' => $document->nomor_revisi,
            'context' => 'master',
        ]);

        return response()->file($path, $this->pdfResponseHeaders($file));
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, $this->pdfResponseHeaders($file));
    }

    private function pdfResponseHeaders(DocumentFile $file): array
    {
        return [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    public function generatedFile(
        Request $request,
        Document $document,
        DynamicFinalDocumentRenderer $renderer,
        RecordDocumentDownload $recordDocumentDownload,
    ): Response {
        $this->authorizeMasterGeneratedPreviewAccess($document);

        $context = PdfDocumentContext::FINAL_DOCUMENT;
        $pdf = $renderer->render($document, $context);

        if ($request->boolean('download')) {
            $recordDocumentDownload->handle($request, $document, null, [
                'name' => $document->nama_dokumen,
                'number' => $this->masterDisplayNumber($document),
                'revision' => $document->nomor_revisi,
                'context' => 'master',
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$renderer->fileName($document, $context).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function authorizeMasterFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->isVisibleMasterRecord($document), 404);
        abort(404);
    }

    private function authorizeMasterGeneratedPreviewAccess(Document $document): void
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->isVisibleMasterRecord($document), 404);
    }

    private function latestGeneratedPrintout(Document $document): ?DocumentFinalArtifact
    {
        return $document->finalArtifacts
            ->where('artifact_type', DocumentFinalArtifact::TYPE_FINAL_DOCUMENT)
            ->whereIn('generation_status', [
                DocumentFinalArtifact::STATUS_GENERATED,
                DocumentFinalArtifact::STATUS_FAILED,
            ])
            ->sortByDesc('generation_number')
            ->first();
    }

    private function whereVisibleMasterRecord($query): void
    {
        $query
            ->whereNull('request_type')
            ->orWhere('request_type', '!=', 'obsolete');
    }

    private function isVisibleMasterRecord(Document $document): bool
    {
        return $document->request_type !== 'obsolete';
    }

    private function canRequestRevision(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::APPROVED) {
            return false;
        }

        $user = $request->user();

        if ($user?->isDeveloper() || $user?->isAdmin()) {
            return true;
        }

        if ($user?->m_department_id === null) {
            return false;
        }

        if ($document->relationLoaded('departments')) {
            return $document->departments->contains('id', $user->m_department_id);
        }

        return $document->departments()
            ->whereKey($user->m_department_id)
            ->exists();
    }

    private function canRestoreMaster(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::OBSOLETE) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function canRequestObsolete(Request $request, Document $document): bool
    {
        return $this->canRequestRevision($request, $document);
    }

    private function canRequestImportedObsolete(Request $request, ImportedExistingDocument $document): bool
    {
        if ($document->document_state !== ImportedExistingDocument::STATE_MASTER) {
            return false;
        }

        return $request->user()?->hasAnyPermission([
            'documents.master.imported.obsolete',
            'documents.obsolete.imports.create',
        ]) ?? false;
    }

    private function appendImportedObsoleteNote(?string $existingNote, string $obsoleteNote): string
    {
        $obsoleteNote = 'Alasan obsolete: '.$obsoleteNote;

        return collect([$existingNote, $obsoleteNote])
            ->filter(fn (?string $note): bool => filled($note))
            ->implode("\n\n");
    }

    private function restoreBlockedMessage(Document $document, Document $activeMaster): string
    {
        $activeVersion = $activeMaster->formatted_revision;

        if ($activeMaster->nomor_revisi > $document->nomor_revisi) {
            return "Versi terbaru {$activeVersion} masih menjadi master. Silakan obsolete-kan versi terbaru dulu.";
        }

        return "Versi {$activeVersion} masih menjadi master. Silakan obsolete-kan versi {$activeVersion} dulu.";
    }

    private function masterDisplayNumber(Document $document): string
    {
        $rootDocument = Document::query()
            ->whereKey($document->revisionRootId())
            ->first();

        return $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen ?: '-';
    }

    private function revisionRequestDisplayNumber(Document $document): ?string
    {
        if ($document->revised_from === null) {
            return null;
        }

        $revisionRequest = Document::query()
            ->where('revised_from', $document->revised_from)
            ->where('request_type', 'revision')
            ->where('nomor_revisi', $document->nomor_revisi)
            ->latest('id')
            ->first();

        if ($revisionRequest?->nomor_dokumen) {
            return $revisionRequest->nomor_dokumen;
        }

        $masterDisplayNumber = $this->masterDisplayNumber($document);

        return $document->nomor_dokumen !== $masterDisplayNumber
            ? $document->nomor_dokumen
            : null;
    }

    private function revisionFormNumber(Document $document): string
    {
        $prefix = match ($document->documentLevel?->kode) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $segments = collect(explode('-', (string) $document->nomor_dokumen))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->filter()
            ->implode('-');
    }

    private function importedMasterQuery(array $filters)
    {
        $query = ImportedExistingDocument::query()
            ->with(['documentLevel', 'documentType', 'businessProcess', 'businessFunction', 'departments', 'uploader'])
            ->where('document_state', ImportedExistingDocument::STATE_MASTER);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_revisi', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"))
                    ->orWhereHas('departments', fn ($query) => $query->where('nama_department', 'like', "%{$search}%"));
            });
        }

        if ($filters['type'] !== '') {
            $query->where('m_document_level_id', $filters['type']);
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        return $query;
    }

    private function presentWorkflowMasterRow(Request $request, Document $document): object
    {
        $rootDocument = Document::query()
            ->whereKey($document->revisionRootId())
            ->first();
        $family = $document->revisionFamily()
            ->load([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
            ])
            ->sortBy('nomor_revisi')
            ->values();
        $publishedWorkflowRevisions = $family
            ->filter(fn (Document $revision): bool => $revision->request_type !== 'obsolete'
                && ($revision->tanggal_terbit !== null || $revision->approved_at !== null)
                && in_array($revision->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true))
            ->values();
        $obsoleteDocuments = $family
            ->where('id', '!=', $document->id)
            ->filter(fn (Document $revision): bool => $revision->status?->nama_status === StatusDocument::OBSOLETE
                && $revision->nomor_revisi < $document->nomor_revisi)
            ->map(function (Document $revision) use ($publishedWorkflowRevisions, $rootDocument): object {
                $nextRevision = $publishedWorkflowRevisions
                    ->where('nomor_revisi', '>', $revision->nomor_revisi)
                    ->sortBy('nomor_revisi')
                    ->first();

                return (object) [
                    'source_type' => 'workflow',
                    'source_id' => $revision->id,
                    'nama_dokumen' => $revision->nama_dokumen,
                    'nomor_dokumen' => $rootDocument?->nomor_dokumen ?: $revision->nomor_dokumen,
                    'nomor_revisi' => $revision->formatted_revision,
                    'tanggal_terbit' => $revision->tanggal_terbit ?? $revision->approved_at,
                    'tanggal_obsolete' => $revision->obsolete_at ?? $nextRevision?->tanggal_terbit ?? $nextRevision?->approved_at,
                    'detail_url' => route('documents.obsolete.show', $revision),
                ];
            });

        $importedObsoleteDocuments = $this->relatedImportedObsoleteForWorkflowMaster($document);

        return (object) [
            'source_type' => 'workflow',
            'source_id' => $document->id,
            'source' => $document,
            'is_imported' => false,
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen ?: '-',
            'nomor_revisi' => $document->formatted_revision,
            'department' => $document->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'proses_bisnis' => $document->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $document->businessFunction?->nama_proses_fungsi,
            'tanggal_terbit' => $document->tanggal_terbit ?? $document->approved_at,
            'detail_url' => route('documents.master.show', $document),
            'can_request_revision' => $this->canRequestRevision($request, $document),
            'obsolete_documents' => $obsoleteDocuments
                ->toBase()
                ->merge($importedObsoleteDocuments)
                ->unique(fn (object $obsolete): string => $obsolete->source_type.'-'.$obsolete->source_id)
                ->sortByDesc(fn (object $obsolete): int => $obsolete->tanggal_obsolete?->timestamp ?? $obsolete->tanggal_terbit?->timestamp ?? 0)
                ->values(),
        ];
    }

    private function presentImportedMasterRow(ImportedExistingDocument $document): object
    {
        return (object) [
            'source_type' => 'imported_existing',
            'source_id' => $document->id,
            'source' => $document,
            'is_imported' => true,
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $document->nomor_dokumen ?: '-',
            'nomor_revisi' => $this->formatImportedRevision($document),
            'department' => $document->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'proses_bisnis' => $document->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $document->businessFunction?->nama_proses_fungsi,
            'tanggal_terbit' => $document->tanggal_terbit,
            'detail_url' => route('documents.master.imported.show', $document),
            'can_request_revision' => true,
            'obsolete_documents' => $this->relatedImportedObsoleteForImportedMaster($document),
        ];
    }

    private function sortPresentedMasterRows(Collection $rows, string $sort): Collection
    {
        $sortedRows = match ($sort) {
            'oldest' => $rows->sortBy(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            'name_asc' => $rows->sortBy(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'name_desc' => $rows->sortByDesc(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'revision_desc' => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%s-%010d-%010d',
                $row->nomor_revisi,
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            default => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
        };

        return $sortedRows->values();
    }

    private function relatedImportedObsoleteForWorkflowMaster(Document $document): Collection
    {
        return $this->importedObsoleteChainForTarget(relatedDocumentId: $document->id);
    }

    private function relatedImportedObsoleteForImportedMaster(ImportedExistingDocument $document): Collection
    {
        return $this->importedObsoleteChainForTarget(relatedImportedExistingDocumentId: $document->id);
    }

    private function importedObsoleteChainForTarget(
        ?int $relatedDocumentId = null,
        ?int $relatedImportedExistingDocumentId = null,
        array $visitedImportedDocumentIds = [],
    ): Collection {
        if ($relatedDocumentId === null && $relatedImportedExistingDocumentId === null) {
            return collect();
        }

        $relations = ImportedExistingDocumentRelation::query()
            ->with(['sourceDocument.businessProcess', 'sourceDocument.businessFunction'])
            ->where('relation_type', ImportedExistingDocumentRelation::SUPERSEDED_BY)
            ->whereHas('sourceDocument', fn ($query) => $query->where('document_state', ImportedExistingDocument::STATE_OBSOLETE))
            ->when(
                $relatedDocumentId !== null,
                fn ($query) => $query->where('related_document_id', $relatedDocumentId),
                fn ($query) => $query->where('related_imported_existing_document_id', $relatedImportedExistingDocumentId),
            )
            ->get();

        return $relations
            ->flatMap(function (ImportedExistingDocumentRelation $relation) use ($visitedImportedDocumentIds): Collection {
                $sourceDocument = $relation->sourceDocument;

                if ($sourceDocument === null || in_array($sourceDocument->id, $visitedImportedDocumentIds, true)) {
                    return collect();
                }

                $visitedImportedDocumentIds[] = $sourceDocument->id;

                return collect([$this->presentImportedObsoleteRow($sourceDocument)])
                    ->merge($this->importedObsoleteChainForTarget(
                        relatedImportedExistingDocumentId: $sourceDocument->id,
                        visitedImportedDocumentIds: $visitedImportedDocumentIds,
                    ));
            })
            ->unique(fn (object $obsolete): string => $obsolete->source_type.'-'.$obsolete->source_id)
            ->sortByDesc(fn (object $obsolete): int => $obsolete->tanggal_obsolete?->timestamp ?? $obsolete->tanggal_terbit?->timestamp ?? 0)
            ->values();
    }

    private function presentImportedObsoleteRow(?ImportedExistingDocument $document): object
    {
        return (object) [
            'source_type' => 'imported_existing',
            'source_id' => $document?->id ?? 0,
            'nama_dokumen' => $document?->nama_dokumen ?: '-',
            'nomor_dokumen' => $document?->nomor_dokumen ?: $document?->nama_dokumen ?: '-',
            'nomor_revisi' => $document ? $this->formatImportedRevision($document) : '-',
            'tanggal_terbit' => $document?->tanggal_terbit,
            'tanggal_obsolete' => $document?->tanggal_obsolete,
            'detail_url' => $document ? route('documents.existing.imports.show', $document) : '#',
        ];
    }

    private function formatImportedRevision(ImportedExistingDocument $document): string
    {
        return filled($document->nomor_revisi) ? (string) $document->nomor_revisi : '-';
    }

    private function importedMasterNote(ImportedExistingDocument $document): string
    {
        return filled($document->catatan)
            ? (string) $document->catatan
            : 'Tidak ada catatan import.';
    }
}
