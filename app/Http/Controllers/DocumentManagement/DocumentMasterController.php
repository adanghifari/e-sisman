<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            ->where('m_status_document_id', $approvedStatusId);

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

        $documents = $query->get();

        $documents->each(function (Document $document) use ($request): void {
            $obsoleteDocuments = $document->obsoleteRevisions;

            if ($document->revisedFrom?->status?->nama_status === StatusDocument::OBSOLETE) {
                $obsoleteDocuments = $obsoleteDocuments->push($document->revisedFrom);
            }

            $document->setRelation(
                'masterObsoleteDocuments',
                $obsoleteDocuments->unique('id')->sortByDesc('approved_at')->values(),
            );
            $document->setAttribute('can_request_revision', $this->canRequestRevision($request, $document));
        });

        $typeOptions = ['' => 'Semua Level'] + DocumentLevel::query()
            ->orderBy('id')
            ->pluck('nama_dokumen', 'id')
            ->all();

        $processOptions = ['' => 'Semua Proses'] + BusinessProcess::query()
            ->orderBy('nama_proses_bisnis')
            ->pluck('nama_proses_bisnis', 'id')
            ->all();

        return view('document-management.master', [
            'documents' => $documents,
            'totalDocuments' => Document::query()->where('m_status_document_id', $approvedStatusId)->count(),
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
            'referenceDocument',
            'revisedFrom.status',
        ]);

        abort_unless(
            in_array($document->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true),
            404,
        );

        return view('document-management.master-detail', [
            'document' => $document,
            'canRequestRevision' => $this->canRequestRevision($request, $document),
            'approvalFlowStages' => $document->documentLevel
                ?->approvalFlows
                ->flatMap(fn ($flow) => $flow->stages)
                ->sortBy('stage_order')
                ->values()
                ?? collect(),
            'contentFiles' => $document->files->whereIn('type_file', ['filled_template', 'imported_document'])->values(),
            'attachmentFiles' => $document->files->where('type_file', 'attachment')->values(),
        ]);
    }

    public function file(Request $request, Document $document, DocumentFile $file, RecordDocumentDownload $recordDocumentDownload): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    private function authorizeMasterFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless(
            in_array($document->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true),
            404,
        );
    }

    private function canRequestRevision(Request $request, Document $document): bool
    {
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
}
