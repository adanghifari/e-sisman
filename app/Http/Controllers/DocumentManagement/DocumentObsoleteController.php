<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\ImportedExistingDocument;
use App\Models\StatusDocument;
use App\Support\DocumentHistory;
use App\Support\FinalDocuments\DocumentWatermarkStamp;
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

class DocumentObsoleteController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'process' => (string) $request->query('process', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        $query = Document::query()
            ->with([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
                'revisedFrom',
            ])
            ->where('m_status_document_id', $obsoleteStatusId)
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

        $workflowObsoleteDocuments = $query->get();
        $importedObsoleteDocuments = $this->importedObsoleteQuery($filters)->get();

        $documents = $this->buildObsoleteFamilyGroups(
            $workflowObsoleteDocuments,
            $importedObsoleteDocuments,
            $filters['sort']
        );

        $totalDocuments = $workflowObsoleteDocuments->count() + $importedObsoleteDocuments->count();

        $typeOptions = ['' => 'Semua Level'] + DocumentLevel::query()
            ->orderBy('id')
            ->pluck('nama_dokumen', 'id')
            ->all();

        $processOptions = ['' => 'Semua Proses'] + BusinessProcess::query()
            ->orderBy('nama_proses_bisnis')
            ->pluck('nama_proses_bisnis', 'id')
            ->all();

        return view('document-management.obsolete.index', [
            'documents' => $documents,
            'totalDocuments' => $totalDocuments,
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'canCreateObsolete' => $request->user()?->hasPermission('documents.obsolete.create') ?? false,
            'canViewImportedExisting' => $request->user()?->hasPermission('documents.existing.imports.view') ?? false,
            'canCreateImportedExisting' => $request->user()?->hasPermission('documents.obsolete.imports.create') ?? false,
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
                'revision_desc' => 'Revisi Tertinggi',
            ],
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

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);

        return view('document-management.obsolete.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'revisionRequestDisplayNumber' => $this->revisionRequestDisplayNumber($document),
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
                ->canRender($document, PdfDocumentContext::finalFor($document)),
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
        ]);
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($this->canAccessRestoreAction($request, $document), 403);

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
                ->route('documents.obsolete.show', $document)
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
        $this->authorizeObsoleteFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file, [
            'name' => $document->nama_dokumen,
            'number' => $document->nomor_dokumen,
            'revision' => $document->nomor_revisi,
            'context' => 'obsolete',
        ]);

        return response()->file($path, $this->pdfResponseHeaders($file));
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeObsoleteFileAccess($document, $file);
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
        $this->authorizeObsoleteGeneratedPreviewAccess($document);

        $context = PdfDocumentContext::finalFor($document);
        $watermarkStamp = null;

        if ($request->boolean('download')) {
            $recordDocumentDownload->handle($request, $document, null, [
                'name' => $document->nama_dokumen,
                'number' => $this->masterDisplayNumber($document),
                'revision' => $document->nomor_revisi,
                'context' => 'obsolete',
            ]);

            $downloadCount = DocumentDownloadLog::query()
                ->where('t_document_id', $document->id)
                ->count();

            $watermarkStamp = DocumentWatermarkStamp::forDownload(
                userName: $request->user()?->name ?? 'PENGGUNA',
                downloadTime: now(),
                downloadCount: max(1, $downloadCount),
            );
        } else {
            $watermarkStamp = DocumentWatermarkStamp::forObsolete(
                documentNumber: $this->masterDisplayNumber($document),
                revision: $document->formatted_revision,
                obsoleteAt: $document->obsolete_at ?? $document->updated_at,
            );
        }

        $pdf = $renderer->render($document, $context, watermarkStamp: $watermarkStamp);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$renderer->fileName($document, $context).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function authorizeObsoleteFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);
        abort(404);
    }

    private function authorizeObsoleteGeneratedPreviewAccess(Document $document): void
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);
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

    private function canRestoreMaster(Request $request, Document $document): bool
    {
        if (! $this->canAccessRestoreAction($request, $document)) {
            return false;
        }

        if ($this->activeMasterInFamily($document) !== null) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function canAccessRestoreAction(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::OBSOLETE) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function activeMasterInFamily(Document $document): ?Document
    {
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);

        return $document->revisionFamily()
            ->first(fn (Document $revision): bool => $revision->id !== $document->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $this->isVisibleMasterRecord($revision));
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

    private function importedObsoleteQuery(array $filters)
    {
        $query = ImportedExistingDocument::query()
            ->with([
                'documentLevel',
                'documentType',
                'businessProcess',
                'businessFunction',
                'departments',
                'uploader',
                'outgoingRelations',
                'incomingImportedRelations',
            ])
            ->where('document_state', ImportedExistingDocument::STATE_OBSOLETE);

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

    private function buildObsoleteFamilyGroups(
        Collection $workflowDocuments,
        Collection $importedDocuments,
        string $sort,
    ): Collection {
        if ($workflowDocuments->isEmpty() && $importedDocuments->isEmpty()) {
            return collect();
        }

        $presentedWf = $workflowDocuments->mapWithKeys(function (Document $doc): array {
            return ["wf:{$doc->id}" => $this->presentWorkflowObsoleteItem($doc)];
        });

        $presentedImp = $importedDocuments->mapWithKeys(function (ImportedExistingDocument $doc): array {
            return ["imp:{$doc->id}" => $this->presentImportedObsoleteItem($doc)];
        });

        $adj = [];
        $addNode = function (string $u) use (&$adj): void {
            if (!isset($adj[$u])) {
                $adj[$u] = [];
            }
        };
        $addEdge = function (string $u, string $v) use (&$adj, $addNode): void {
            $addNode($u);
            $addNode($v);
            $adj[$u][] = $v;
            $adj[$v][] = $u;
        };

        foreach ($presentedWf->keys() as $key) {
            $addNode($key);
        }
        foreach ($presentedImp->keys() as $key) {
            $addNode($key);
        }

        foreach ($workflowDocuments as $wfDoc) {
            $rootId = $wfDoc->revisionRootId();
            $addEdge("wf:{$wfDoc->id}", "wf-root:{$rootId}");

            $masterNum = Str::upper(trim($wfDoc->obsolete_display_number ?? $this->masterDisplayNumber($wfDoc)));
            if ($masterNum !== '' && $masterNum !== '-') {
                $addEdge("wf:{$wfDoc->id}", "docnum:{$masterNum}");
            }
            $docNum = Str::upper(trim((string) $wfDoc->nomor_dokumen));
            if ($docNum !== '' && $docNum !== '-' && $docNum !== $masterNum) {
                $addEdge("wf:{$wfDoc->id}", "docnum:{$docNum}");
            }
        }

        foreach ($importedDocuments as $impDoc) {
            $impNum = Str::upper(trim((string) $impDoc->nomor_dokumen));
            if ($impNum !== '' && $impNum !== '-') {
                $addEdge("imp:{$impDoc->id}", "docnum:{$impNum}");
            }
        }

        $wfIds = $workflowDocuments->pluck('id')->all();
        $impIds = $importedDocuments->pluck('id')->all();

        if (!empty($wfIds) || !empty($impIds)) {
            $relations = DocumentRelation::query()
                ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
                ->where(function ($q) use ($wfIds, $impIds) {
                    $hasClause = false;
                    if (!empty($wfIds)) {
                        $q->whereIn('source_document_id', $wfIds)
                          ->orWhereIn('target_document_id', $wfIds);
                        $hasClause = true;
                    }
                    if (!empty($impIds)) {
                        if ($hasClause) {
                            $q->orWhereIn('source_imported_existing_document_id', $impIds)
                              ->orWhereIn('target_imported_existing_document_id', $impIds);
                        } else {
                            $q->whereIn('source_imported_existing_document_id', $impIds)
                              ->orWhereIn('target_imported_existing_document_id', $impIds);
                        }
                    }
                })
                ->get();

            foreach ($relations as $rel) {
                $src = $rel->source_document_id
                    ? "wf:{$rel->source_document_id}"
                    : ($rel->source_imported_existing_document_id ? "imp:{$rel->source_imported_existing_document_id}" : null);

                $tgt = $rel->target_document_id
                    ? "wf:{$rel->target_document_id}"
                    : ($rel->target_imported_existing_document_id ? "imp:{$rel->target_imported_existing_document_id}" : null);

                if ($src && $tgt && (isset($adj[$src]) || isset($adj[$tgt]))) {
                    $addEdge($src, $tgt);
                }
            }
        }

        $visited = [];
        $families = collect();

        foreach ($adj as $node => $neighbors) {
            if (!str_starts_with($node, 'wf:') && !str_starts_with($node, 'imp:')) {
                continue;
            }
            if (isset($visited[$node])) {
                continue;
            }

            $queue = [$node];
            $visited[$node] = true;
            $componentDocs = [];

            while (!empty($queue)) {
                $curr = array_shift($queue);
                if (str_starts_with($curr, 'wf:') && isset($presentedWf[$curr])) {
                    $componentDocs[$curr] = $presentedWf[$curr];
                } elseif (str_starts_with($curr, 'imp:') && isset($presentedImp[$curr])) {
                    $componentDocs[$curr] = $presentedImp[$curr];
                }

                foreach ($adj[$curr] ?? [] as $neighbor) {
                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            if (!empty($componentDocs)) {
                $familyCollection = collect(array_values($componentDocs));

                $sortedFamily = $familyCollection
                    ->sortByDesc(fn (object $item): string => sprintf(
                        '%010d-%010d-%010d-%010d',
                        $item->numeric_revision,
                        $item->tanggal_obsolete?->timestamp ?? 0,
                        $item->tanggal_terbit?->timestamp ?? 0,
                        $item->source_id,
                    ))
                    ->values();

                $parent = clone $sortedFamily->first();

                // Clean master base document number if parent is a revision form starting with FM
                $baseNumber = $familyCollection
                    ->map(fn (object $i): string => (string) ($i->obsolete_display_number ?: $i->nomor_dokumen))
                    ->first(fn (string $num): bool => !Str::startsWith($num, ['FM', 'fm']) && $num !== '' && $num !== '-');

                if ($baseNumber && Str::startsWith((string) $parent->nomor_dokumen, ['FM', 'fm'])) {
                    $parent->nomor_dokumen = $baseNumber;
                    $parent->obsolete_display_number = $baseNumber;
                }

                $parent->child_documents = $sortedFamily->slice(1)->values();
                $parent->obsoleteChildDocuments = $parent->child_documents;
                $families->push($parent);
            }
        }

        return $this->sortPresentedObsoleteRows($families, $sort);
    }

    private function presentWorkflowObsoleteItem(Document $doc): object
    {
        $rootDocument = $doc->revised_from !== null
            ? Document::query()->whereKey($doc->revisionRootId())->first()
            : null;

        $masterNumber = $rootDocument?->nomor_dokumen ?: $doc->nomor_dokumen ?: '-';
        $publishedAt = $doc->tanggal_terbit ?? $doc->approved_at;

        return (object) [
            'source_type' => 'workflow',
            'source_id' => $doc->id,
            'source' => $doc,
            'id' => $doc->id,
            'is_imported' => false,
            'nama_dokumen' => $doc->nama_dokumen,
            'nomor_dokumen' => $masterNumber,
            'obsolete_display_number' => $masterNumber,
            'nomor_revisi' => $doc->formatted_revision,
            'formatted_revision' => $doc->formatted_revision,
            'numeric_revision' => (int) $doc->nomor_revisi,
            'department' => $doc->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'departments' => $doc->departments,
            'proses_bisnis' => $doc->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $doc->businessFunction?->nama_proses_fungsi,
            'businessProcess' => $doc->businessProcess,
            'businessFunction' => $doc->businessFunction,
            'tanggal_terbit' => $publishedAt,
            'approved_at' => $doc->approved_at,
            'tanggal_obsolete' => $doc->obsolete_at,
            'detail_url' => route('documents.obsolete.show', $doc),
            'child_documents' => collect(),
            'obsoleteChildDocuments' => collect(),
        ];
    }

    private function presentImportedObsoleteItem(ImportedExistingDocument $doc): object
    {
        $revisionStr = $this->formatImportedRevision($doc);

        return (object) [
            'source_type' => 'imported_existing',
            'source_id' => $doc->id,
            'source' => $doc,
            'id' => $doc->id,
            'is_imported' => true,
            'nama_dokumen' => $doc->nama_dokumen,
            'nomor_dokumen' => $doc->nomor_dokumen ?: '-',
            'obsolete_display_number' => $doc->nomor_dokumen ?: '-',
            'nomor_revisi' => $revisionStr,
            'formatted_revision' => $revisionStr,
            'numeric_revision' => $this->parseRevisionNumber($doc->nomor_revisi),
            'department' => $doc->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'departments' => $doc->departments,
            'proses_bisnis' => $doc->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $doc->businessFunction?->nama_proses_fungsi,
            'businessProcess' => $doc->businessProcess,
            'businessFunction' => $doc->businessFunction,
            'tanggal_terbit' => $doc->tanggal_terbit,
            'approved_at' => null,
            'tanggal_obsolete' => $doc->tanggal_obsolete,
            'detail_url' => route('documents.existing.imports.show', $doc),
            'child_documents' => collect(),
            'obsoleteChildDocuments' => collect(),
        ];
    }

    private function sortPresentedObsoleteRows(Collection $rows, string $sort): Collection
    {
        $sortedRows = match ($sort) {
            'oldest' => $rows->sortBy(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            'name_asc' => $rows->sortBy(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'name_desc' => $rows->sortByDesc(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'revision_desc' => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d-%010d',
                $row->numeric_revision,
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            default => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
        };

        return $sortedRows->values();
    }

    private function parseRevisionNumber($revision): int
    {
        if ($revision === null || $revision === '' || $revision === '-') {
            return 0;
        }

        if (is_int($revision)) {
            return $revision;
        }

        $str = trim((string) $revision);
        if (str_contains($str, '.')) {
            $parts = explode('.', $str);
            $major = (int) preg_replace('/[^\d]/', '', $parts[0] ?? '0');
            $minor = (int) preg_replace('/[^\d]/', '', $parts[1] ?? '0');

            return ($major * 100) + $minor;
        }

        $clean = preg_replace('/[^\d]/', '', $str);

        return $clean !== '' ? (int) $clean : 0;
    }

    private function formatImportedRevision(ImportedExistingDocument $document): string
    {
        return filled($document->nomor_revisi) ? (string) $document->nomor_revisi : '-';
    }
}
