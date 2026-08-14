<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Role;
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
        ]);

        return view('document-management.approval-detail', [
            'document' => $document,
            'activeApproval' => $this->activeApproval($request, $document),
            'approvalFlowStages' => $this->approvalFlowStages($document),
            'canManageApproverAssignment' => $this->canManageApproverAssignment($request, $document),
            'assignableUsers' => User::query()->with('department')->orderBy('name')->get(),
            'contentFiles' => $document->files->whereIn('type_file', ['filled_template', 'imported_document'])->values(),
            'attachmentFiles' => $document->files->where('type_file', 'attachment')->values(),
        ]);
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
            ->with('status', 'Dokumen berhasil di-approve.');
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
            ->with('status', 'Dokumen berhasil ditolak.');
    }

    public function assign(Request $request, Document $document): RedirectResponse
    {
        abort_unless($this->canManageApproverAssignment($request, $document), 403);

        $stages = $this->approvalFlowStages($document);

        if ($stages->isEmpty()) {
            return back()->withErrors([
                'stage_approvers' => 'Belum ada aturan tahap approval.',
            ])->withInput();
        }

        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);
        $waitingStatus = ApprovalStatus::findByCode(ApprovalStatus::WAITING);
        $activeStageOrder = $this->activeStageOrderForAssignment($document, $stages);

        foreach ($stages as $stage) {
            $userIds = $this->stageApproverIds($request, $document, $stage);

            if ($userIds->isEmpty()) {
                return back()->withErrors([
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak boleh kosong.",
                ])->withInput();
            }

            if (User::query()->whereIn('id', $userIds)->count() !== $userIds->count()) {
                return back()->withErrors([
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak valid.",
                ])->withInput();
            }
        }

        $approvedStageError = $this->approvedStageAssignmentError($request, $document, $stages);

        if ($approvedStageError !== null) {
            return back()->withErrors($approvedStageError)->withInput();
        }

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $role = Role::query()->firstOrCreate(['nama_role' => $stage->nama_tahap]);
            $stageStatus = $stage->stage_order === $activeStageOrder ? $pendingStatus : $waitingStatus;
            $userIds = $this->stageApproverIds($request, $document, $stage);

            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('role_id', $role->id)
                ->where('stages', $stageLabel)
                ->whereNull('responded_at')
                ->whereNotIn('user_id', $userIds)
                ->delete();

            foreach ($userIds as $userId) {
                $approval = Approval::query()->firstOrNew([
                    't_document_id' => $document->id,
                    'user_id' => $userId,
                    'role_id' => $role->id,
                ]);

                if ($approval->exists && $approval->responded_at !== null) {
                    continue;
                }

                $approval->fill([
                    'm_approval_status_id' => $stageStatus->id,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'responded_at' => null,
                    'created_at' => $approval->created_at ?? now(),
                    'stages' => $stageLabel,
                    'catatan' => null,
                ])->save();
            }
        }

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('status', 'Approver berhasil disimpan.');
    }

    public function file(Request $request, Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($request, $document);
        abort_unless($file->t_document_id === $document->id, 404);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

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
            $user->canAssignDocument($document) || $this->hasActiveApproval($request, $document),
            403,
        );
    }

    private function hasActiveApproval(Request $request, Document $document): bool
    {
        return $document->approvals()
            ->where('user_id', $request->user()->id)
            ->whereNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
            ->exists();
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
        return $document->documentLevel
            ?->approvalFlows
            ->flatMap(fn ($flow) => $flow->stages)
            ->sortBy('stage_order')
            ->values()
            ?? collect();
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
        $userIds = collect($request->input($inputKey, []))
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();

        if (
            $stage->stage_order === 1
            && $userIds->isEmpty()
            && $document->official_preparer_id !== null
            && ! $this->hasStageAssignment($document, $stage)
        ) {
            return collect([(int) $document->official_preparer_id]);
        }

        return $userIds;
    }

    private function hasStageAssignment(Document $document, ApprovalFlowStage $stage): bool
    {
        $stageLabel = $stage->display_label ?: 'Approval';

        return Approval::query()
            ->where('t_document_id', $document->id)
            ->where('stages', $stageLabel)
            ->exists();
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

        $document->update([
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::APPROVED)->id,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
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
