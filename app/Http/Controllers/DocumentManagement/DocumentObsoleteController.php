<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\StatusDocument;
use App\Support\DocumentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            });

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

        $obsoleteDocuments = $query->get();
        $obsoleteDocuments->each(function (Document $document): void {
            $rootDocument = $document->revised_from !== null
                ? Document::query()->whereKey($document->revisionRootId())->first()
                : null;

            $document->setAttribute('obsolete_display_number', $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen);
        });
        $documents = $obsoleteDocuments
            ->groupBy(fn (Document $document): int => $document->revisionRootId())
            ->map(function ($family): Document {
                $sortedFamily = $family
                    ->sortByDesc(fn (Document $document): string => sprintf(
                        '%010d-%010d-%010d',
                        $document->nomor_revisi,
                        $document->approved_at?->timestamp ?? 0,
                        $document->id,
                    ))
                    ->values();
                $latestDocument = $sortedFamily->first();

                $latestDocument->setRelation(
                    'obsoleteChildDocuments',
                    $sortedFamily
                        ->where('id', '!=', $latestDocument->id)
                        ->values(),
                );

                return $latestDocument;
            })
            ->values();

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
            'totalDocuments' => $obsoleteDocuments->count(),
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'canCreateObsolete' => $request->user()?->hasPermission('documents.obsolete.create') ?? false,
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
            'approvals.status',
            'approvals.approver',
            'approvals.role',
            'documentLevel.approvalFlows.stages',
            'revisedFrom.status',
        ]);

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_if($document->request_type === 'obsolete', 404);

        return view('document-management.obsolete.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'canRestoreMaster' => $this->canRestoreMaster($request, $document),
            'approvalFlowStages' => $document->documentLevel
                ?->approvalFlows
                ->flatMap(fn ($flow) => $flow->stages)
                ->sortBy('stage_order')
                ->values()
                ?? collect(),
            'contentFiles' => $document->files->whereIn('type_file', ['filled_template', 'imported_document', 'revision_content'])->values(),
            'attachmentFiles' => $document->files->whereIn('type_file', ['attachment', 'revision_form'])->values(),
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
        ]);
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($this->canRestoreMaster($request, $document), 403);

        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);
        $family = $document->revisionFamily();
        $familyIds = $family->pluck('id');
        $activeMaster = $family
            ->first(fn (Document $revision): bool => $revision->id !== $document->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $revision->request_type !== 'obsolete');

        if ($activeMaster !== null) {
            return redirect()
                ->route('documents.obsolete.show', $document)
                ->with('restore_warning', [
                    'title' => 'Belum Bisa Dijadikan Master',
                    'message' => $this->restoreBlockedMessage($document, $activeMaster),
                ]);
        }

        DB::transaction(function () use ($document, $familyIds, $approvedStatus, $obsoleteStatus): void {
            Document::query()
                ->whereIn('id', $familyIds)
                ->where('id', '!=', $document->id)
                ->where('m_status_document_id', $approvedStatus->id)
                ->update([
                    'm_status_document_id' => $obsoleteStatus->id,
                ]);

            $document->update([
                'm_status_document_id' => $approvedStatus->id,
                'approved_at' => now(),
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

        $recordDocumentDownload->handle($request, $document, $file);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeObsoleteFileAccess($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    private function authorizeObsoleteFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
    }

    private function canRestoreMaster(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::OBSOLETE) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
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
}
