<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->where('m_status_document_id', $approvedStatusId)
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

        $documents = $query->get()
            ->groupBy(fn (Document $document): int => $document->revisionRootId())
            ->map(fn ($family): Document => $family
                ->sortByDesc(fn (Document $document): string => sprintf(
                    '%010d-%010d-%010d',
                    $document->nomor_revisi,
                    $document->approved_at?->timestamp ?? 0,
                    $document->id,
                ))
                ->first())
            ->values();

        $documents->each(function (Document $document) use ($request): void {
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
            $obsoleteDocuments = $family
                ->where('id', '!=', $document->id)
                ->filter(fn (Document $revision): bool => $revision->status?->nama_status === StatusDocument::OBSOLETE
                    && $revision->nomor_revisi < $document->nomor_revisi)
                ->map(function (Document $revision) use ($family, $rootDocument): Document {
                    $nextRevision = $family
                        ->where('nomor_revisi', '>', $revision->nomor_revisi)
                        ->sortBy('nomor_revisi')
                        ->first();

                    $revision->setAttribute(
                        'master_obsolete_date',
                        $nextRevision?->tanggal_terbit ?? $nextRevision?->approved_at,
                    );
                    $revision->setAttribute('master_display_number', $rootDocument?->nomor_dokumen ?: $revision->nomor_dokumen);

                    return $revision;
                });

            $document->setRelation(
                'masterObsoleteDocuments',
                $obsoleteDocuments->unique('id')->sortByDesc('approved_at')->values(),
            );
            $document->setAttribute('master_display_number', $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen);
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

        abort_unless(
            $document->status?->nama_status === StatusDocument::APPROVED,
            404,
        );
        abort_if($document->request_type === 'obsolete', 404);

        return view('document-management.master.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
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
            'attachmentFiles' => $document->files->whereIn('type_file', ['attachment', 'revision_form'])->values(),
        ]);
    }

    public function obsolete(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status', 'documentLevel', 'departments');

        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->canRequestObsolete($request, $document), 403);

        $validated = $request->validate([
            'catatan_obsolete' => ['required', 'string', 'max:1000'],
        ]);

        $level = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $type = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();
        $status = StatusDocument::findByName(StatusDocument::PROPOSED);

        $requestDocument = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
            'user_id' => $request->user()->id,
            'official_preparer_id' => $document->official_preparer_id ?: $request->user()->id,
            'reference' => null,
            'revised_from' => $document->id,
            'request_type' => 'obsolete',
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $this->revisionFormNumber($document),
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
                && $revision->request_type !== 'obsolete');

        if ($activeMaster !== null) {
            return redirect()
                ->route('documents.master.show', $document)
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
        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
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
}
