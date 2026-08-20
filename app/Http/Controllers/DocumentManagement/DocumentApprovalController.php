<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentApprovalController extends Controller
{
    private const OFFICIAL_PREPARER_STAGE = 'TTD Penyusun Resmi';

    public function show(Request $request, Document $document): View
    {
        $this->authorizeDocumentAccess($request, $document);

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
            'revisedFrom.documentLevel.approvalFlows.stages',
            'revisedFrom.status',
            'revisedFrom.documentType',
            'revisedFrom.businessProcess',
            'revisedFrom.businessFunction',
            'revisedFrom.departments',
            'revisedFrom.files.uploader',
        ]);
        $contentDocument = $document->request_type === 'obsolete' && $document->revisedFrom !== null
            ? $document->revisedFrom
            : $document;

        return view('document-management.approval-detail', [
            'document' => $document,
            'contentDocument' => $contentDocument,
            'displayDocumentNumber' => $this->displayDocumentNumber($document, $contentDocument),
            'activeApproval' => $this->activeApproval($request, $document),
            'approvalFlowStages' => $this->approvalFlowStages($document),
            'approvalFlowDocumentLevel' => $this->approvalFlowDocumentLevel($document),
            'canManageApproverAssignment' => $this->canManageApproverAssignment($request, $document),
            'assignableUsers' => User::query()->with('department')->orderBy('name')->get(),
            'contentFiles' => $contentDocument->files->whereIn('type_file', [
                'filled_template',
                'imported_document',
                'revision_content',
                'revision_form',
                'revision_before',
                'revision_after',
            ])->values(),
            'attachmentFiles' => $contentDocument->files->where('type_file', 'attachment')->values(),
        ]);
    }

    private function displayDocumentNumber(Document $document, Document $contentDocument): string
    {
        if ($document->request_type === 'obsolete') {
            return $contentDocument->nomor_dokumen ?: $document->nomor_dokumen ?: '-';
        }

        if ($document->request_type !== 'revision' || $document->revisedFrom === null) {
            return $document->nomor_dokumen ?: '-';
        }

        if (str_starts_with((string) $document->nomor_dokumen, 'FM')) {
            return $document->nomor_dokumen;
        }

        $source = $document->revisedFrom;
        $prefix = match ($source->documentLevel?->kode) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $segments = collect(explode('-', (string) $source->nomor_dokumen))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->filter()
            ->implode('-') ?: ($document->nomor_dokumen ?: '-');
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $approval = $this->activeApproval($request, $document);
        abort_if(! $approval, 404);

        DB::transaction(function () use ($request, $document, $approval): void {
            $approval->update([
                'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id,
                'responded_at' => now(),
                'catatan' => $request->string('catatan')->trim()->value() ?: null,
            ]);

            $this->advanceApprovalFlow($document->refresh());
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('document_success', [
                'title' => 'Dokumen Berhasil Disetujui',
                'message' => 'Approval Anda sudah tercatat pada riwayat dokumen.',
            ]);
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'max:1000'],
        ]);

        $approval = $this->activeApproval($request, $document);
        abort_if(! $approval, 404);

        DB::transaction(function () use ($document, $approval, $validated): void {
            $rejectedStatus = ApprovalStatus::findByCode(ApprovalStatus::REJECTED);
            $terminatedStatus = ApprovalStatus::findByCode(ApprovalStatus::TERMINATED);

            $approval->update([
                'm_approval_status_id' => $rejectedStatus->id,
                'responded_at' => now(),
                'catatan' => $validated['catatan'],
            ]);

            Approval::query()
                ->where('t_document_id', $document->id)
                ->whereKeyNot($approval->id)
                ->whereHas('status', fn ($query) => $query->whereIn('kode_status', [
                    ApprovalStatus::PENDING,
                    ApprovalStatus::WAITING,
                ]))
                ->update([
                    'm_approval_status_id' => $terminatedStatus->id,
                    'responded_at' => now(),
                ]);

            $document->update([
                'm_status_document_id' => StatusDocument::findByName(StatusDocument::REJECTED)->id,
                'rejected_at' => now(),
            ]);
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('document_success', [
                'title' => 'Dokumen Berhasil Ditolak',
                'message' => 'Catatan penolakan Anda sudah tersimpan pada riwayat dokumen.',
            ]);
    }

    public function assign(Request $request, Document $document): RedirectResponse
    {
        abort_unless($this->canManageApproverAssignment($request, $document), 403);

        $stages = $this->approvalFlowStages($document);

        if ($stages->isEmpty()) {
            return $this->assignmentErrorRedirect($document, [
                'stage_approvers' => 'Belum ada aturan tahap approval.',
            ]);
        }

        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);
        $waitingStatus = ApprovalStatus::findByCode(ApprovalStatus::WAITING);
        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);
        $activeStageOrder = $this->activeStageOrderForAssignment($document, $stages);
        $officialPreparerSignature = $this->officialPreparerSignature($document);

        foreach ($stages as $stage) {
            $userIds = $this->stageApproverIds($request, $document, $stage);

            if ($userIds->isEmpty()) {
                return $this->assignmentErrorRedirect($document, [
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak boleh kosong.",
                ]);
            }

            if (User::query()->whereIn('id', $userIds)->count() !== $userIds->count()) {
                return $this->assignmentErrorRedirect($document, [
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak valid.",
                ]);
            }
        }

        $approvedStageError = $this->approvedStageAssignmentError($request, $document, $stages);

        if ($approvedStageError !== null) {
            return $this->assignmentErrorRedirect($document, $approvedStageError);
        }

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $stageStatus = $stage->stage_order === $activeStageOrder ? $pendingStatus : $waitingStatus;
            $userIds = $this->stageApproverIds($request, $document, $stage);

            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('stages', $stageLabel)
                ->whereNull('responded_at')
                ->whereNotIn('user_id', $userIds)
                ->delete();

            foreach ($userIds as $userId) {
                $approval = Approval::query()->firstOrNew([
                    't_document_id' => $document->id,
                    'user_id' => $userId,
                    'stages' => $stageLabel,
                ]);

                if ($approval->exists && $approval->responded_at !== null) {
                    continue;
                }

                $alreadySignedAsOfficialPreparer = $document->official_preparer_id === $userId
                    && $officialPreparerSignature !== null;

                $approval->fill([
                    'm_approval_status_id' => $alreadySignedAsOfficialPreparer ? $approvedStatus->id : $stageStatus->id,
                    'role_id' => null,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'responded_at' => $alreadySignedAsOfficialPreparer
                        ? ($officialPreparerSignature->responded_at ?? $officialPreparerSignature->assigned_at ?? now())
                        : null,
                    'created_at' => $approval->created_at ?? now(),
                    'catatan' => null,
                ])->save();
            }
        }

        $this->markRevisionRequestAsAssigned($document);
        $this->advanceApprovalFlow($document);

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('status', 'Approver berhasil disimpan.');
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function assignmentErrorRedirect(Document $document, array $errors): RedirectResponse
    {
        return redirect()
            ->route('documents.approval.show', $document)
            ->withErrors($errors)
            ->withInput();
    }

    private function markRevisionRequestAsAssigned(Document $document): void
    {
        if ($document->request_type !== 'revision') {
            return;
        }

        if ($document->documentLevel?->kode !== 'level-4') {
            return;
        }

        $revisionTypeId = DocumentType::query()
            ->where('nama_types', 'Revisi')
            ->value('id');

        if ($revisionTypeId === null) {
            return;
        }

        $document->forceFill([
            'm_document_types_id' => $revisionTypeId,
        ])->save();
    }

    public function file(Request $request, Document $document, DocumentFile $file, RecordDocumentDownload $recordDocumentDownload): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($request, $document);
        abort_unless($file->t_document_id === $document->id, 404);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    public function preview(Request $request, Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($request, $document);
        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $sourcePath = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($sourcePath), 404);

        return response()->file($sourcePath, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $document->user_id === $user->id
                || $document->official_preparer_id === $user->id
                || $user->canAssignDocument($document)
                || $this->hasAccessibleApproval($request, $document),
            403,
        );
    }

    private function hasAccessibleApproval(Request $request, Document $document): bool
    {
        return $document->approvals()
            ->where('user_id', $request->user()->id)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('responded_at')
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNull('responded_at')
                            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING));
                    });
            })
            ->exists();
    }

    private function officialPreparerSignature(Document $document): ?Approval
    {
        if ($document->official_preparer_id === null) {
            return null;
        }

        return $document->approvals()
            ->where('user_id', $document->official_preparer_id)
            ->where('stages', self::OFFICIAL_PREPARER_STAGE)
            ->whereNotNull('responded_at')
            ->first();
    }

    private function activeApproval(Request $request, Document $document): ?Approval
    {
        $query = $document->approvals()
            ->with(['status', 'approver', 'role'])
            ->whereNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING));

        if (! $request->user()->isDeveloper()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->orderByDesc('assigned_at')->first();
    }

    private function approvalFlowStages(Document $document)
    {
        return $this->approvalFlowDocumentLevel($document)
            ?->approvalFlows
            ->flatMap(fn ($flow) => $flow->stages)
            ->sortBy('stage_order')
            ->values()
            ?? collect();
    }

    private function approvalFlowDocumentLevel(Document $document): ?DocumentLevel
    {
        $document->loadMissing([
            'documentLevel.approvalFlows.stages',
            'revisedFrom.documentLevel.approvalFlows.stages',
        ]);

        if ($document->documentLevel?->kode === 'level-4' && $document->revisedFrom?->documentLevel !== null) {
            return $document->revisedFrom->documentLevel;
        }

        return $document->documentLevel;
    }

    private function isDocumentAssignmentLocked(Document $document): bool
    {
        $lockedStatuses = [StatusDocument::APPROVED, StatusDocument::REJECTED];

        if ($document->relationLoaded('status')) {
            return in_array($document->status?->nama_status, $lockedStatuses, true);
        }

        return $document->status()
            ->whereIn('nama_status', $lockedStatuses)
            ->exists();
    }

    private function canManageApproverAssignment(Request $request, Document $document): bool
    {
        if ($this->isDocumentAssignmentLocked($document)) {
            return false;
        }

        return $request->user()->isDeveloper() || $request->user()->canAssignDocument($document);
    }

    private function stageApproverIds(Request $request, Document $document, ApprovalFlowStage $stage): Collection
    {
        $inputKey = "stage_approvers.{$stage->id}";
        return collect($request->input($inputKey, []))
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();
    }

    /**
     * @return array<string, string>|null
     */
    private function approvedStageAssignmentError(Request $request, Document $document, Collection $stages): ?array
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $field = "stage_approvers.{$stage->id}";
            $requestedUserIds = $this->stageApproverIds($request, $document, $stage);
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->where('stages', $stageLabel)
                ->get();

            if ($stageApprovals->isEmpty()) {
                continue;
            }

            $respondedUserIds = $stageApprovals
                ->filter(fn (Approval $approval): bool => $approval->responded_at !== null)
                ->pluck('user_id')
                ->values();

            if ($respondedUserIds->diff($requestedUserIds)->isNotEmpty()) {
                return [$field => "Approver tahap {$stage->stage_order} yang sudah memberikan respon tidak boleh dihapus atau diganti."];
            }

            $approvedUserIds = $stageApprovals
                ->where('m_approval_status_id', $approvedStatusId)
                ->pluck('user_id')
                ->values();

            if ($approvedUserIds->diff($requestedUserIds)->isNotEmpty()) {
                return [$field => "Approver tahap {$stage->stage_order} yang sudah approve tidak boleh dihapus atau diganti."];
            }

            $isStageFullyApproved = $stageApprovals->every(
                fn (Approval $approval): bool => $approval->m_approval_status_id === $approvedStatusId,
            );

            if (! $isStageFullyApproved) {
                continue;
            }

            $existingUserIds = $stageApprovals->pluck('user_id')->sort()->values();

            if ($existingUserIds->all() !== $requestedUserIds->sort()->values()->all()) {
                return [$field => "Tahap {$stage->stage_order} sudah approved dan tidak boleh diubah."];
            }
        }

        return null;
    }

    private function advanceApprovalFlow(Document $document): void
    {
        if ($this->activateNextStageIfCurrentStageComplete($document)) {
            return;
        }

        $this->markDocumentApprovedWhenComplete($document);
    }

    private function activateNextStageIfCurrentStageComplete(Document $document): bool
    {
        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);
        $waitingStatus = ApprovalStatus::findByCode(ApprovalStatus::WAITING);
        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);
        $stages = $this->approvalFlowStages($document);

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->where('stages', $stageLabel);

            if ((clone $stageApprovals)->where('m_approval_status_id', $pendingStatus->id)->exists()) {
                return false;
            }

            if (
                (clone $stageApprovals)->exists()
                && ! (clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatus->id)->exists()
            ) {
                continue;
            }

            $activated = (clone $stageApprovals)
                ->where('m_approval_status_id', $waitingStatus->id)
                ->update([
                    'm_approval_status_id' => $pendingStatus->id,
                    'assigned_at' => now(),
                ]);

            return $activated > 0;
        }

        return false;
    }

    private function markDocumentApprovedWhenComplete(Document $document): void
    {
        if (! $this->isApprovalComplete($document)) {
            return;
        }

        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);

        $document->update([
            'm_status_document_id' => $approvedStatus->id,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        $document->refresh();

        if ($document->request_type === 'obsolete') {
            $this->obsoleteSourceMasterDocument($document);

            return;
        }

        $this->promoteRevisionRequestToMaster($document);
        $document->refresh();
        $this->obsoletePreviousApprovedRevisions($document, $approvedStatus);
    }

    private function promoteRevisionRequestToMaster(Document $document): void
    {
        if ($document->request_type !== 'revision' || $document->revised_from === null) {
            return;
        }

        $source = Document::query()
            ->select([
                'id',
                'm_document_level_id',
                'm_document_types_id',
                'reference',
                'nomor_dokumen',
            ])
            ->find($document->revised_from);

        if ($source === null) {
            return;
        }

        $document->update([
            'm_document_level_id' => $source->m_document_level_id,
            'm_document_types_id' => $source->m_document_types_id,
            'reference' => $source->reference,
            'nomor_dokumen' => $source->nomor_dokumen,
        ]);
    }

    private function obsoleteSourceMasterDocument(Document $document): void
    {
        if ($document->revised_from === null) {
            return;
        }

        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);

        Document::query()
            ->whereKey($document->revised_from)
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::APPROVED))
            ->update([
                'm_status_document_id' => $obsoleteStatus->id,
            ]);
    }

    private function obsoletePreviousApprovedRevisions(Document $document, StatusDocument $approvedStatus): void
    {
        if ($document->revised_from === null) {
            return;
        }

        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);

        $document->revisionFamily()
            ->where('id', '!=', $document->id)
            ->where('m_status_document_id', $approvedStatus->id)
            ->each(function (Document $revision) use ($obsoleteStatus): void {
                $revision->update([
                    'm_status_document_id' => $obsoleteStatus->id,
                ]);
            });
    }

    private function isApprovalComplete(Document $document): bool
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;
        $rejectedStatusId = ApprovalStatus::findByCode(ApprovalStatus::REJECTED)->id;
        $stages = $this->approvalFlowStages($document);

        $approvalQuery = Approval::query()->where('t_document_id', $document->id);

        if ((clone $approvalQuery)->where('m_approval_status_id', $rejectedStatusId)->exists()) {
            return false;
        }

        if ($stages->isEmpty()) {
            return (clone $approvalQuery)->exists()
                && ! (clone $approvalQuery)
                    ->where('m_approval_status_id', '!=', $approvedStatusId)
                    ->exists();
        }

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->where('stages', $stageLabel);

            if (! (clone $stageApprovals)->exists()) {
                return false;
            }

            if ((clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatusId)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function activeStageOrderForAssignment(Document $document, Collection $stages): int
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->where('stages', $stageLabel);

            if (
                ! (clone $stageApprovals)->exists()
                || (clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatusId)->exists()
            ) {
                return $stage->stage_order;
            }
        }

        return $stages->last()?->stage_order ?? 1;
    }
}
