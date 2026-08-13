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

            $this->markDocumentApprovedWhenComplete($document->refresh());
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
            $approval->update([
                'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::REJECTED)->id,
                'responded_at' => now(),
                'catatan' => $validated['catatan'],
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
        $this->authorizeDocumentAccess($request, $document);

        $stages = $this->approvalFlowStages($document);

        if ($stages->isEmpty()) {
            return back()->withErrors([
                'stage_approvers' => 'Belum ada aturan tahap approval.',
            ])->withInput();
        }

        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);

        foreach ($stages as $stage) {
            $userIds = collect($request->input("stage_approvers.{$stage->id}", []))
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values();

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

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $role = Role::query()->firstOrCreate(['nama_role' => $stage->nama_tahap]);
            $userIds = collect($request->input("stage_approvers.{$stage->id}", []))
                ->filter()
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values();

            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('role_id', $role->id)
                ->where('stages', $stageLabel)
                ->whereNull('responded_at')
                ->whereNotIn('user_id', $userIds)
                ->delete();

            foreach ($userIds as $userId) {
                Approval::query()->updateOrCreate(
                    [
                        't_document_id' => $document->id,
                        'user_id' => $userId,
                        'role_id' => $role->id,
                    ],
                    [
                        'm_approval_status_id' => $pendingStatus->id,
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => now(),
                        'responded_at' => null,
                        'created_at' => now(),
                        'stages' => $stageLabel,
                        'catatan' => null,
                    ],
                );
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
        if ($request->user()->isDeveloper()) {
            return;
        }

        abort_unless(
            $document->approvals()->where('user_id', $request->user()->id)->exists(),
            403,
        );
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
}
