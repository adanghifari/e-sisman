<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\Document;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DocumentInboxController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $this->filters($request);
        $myTasks = $this->myTasks($request);
        $myProcessedHistory = $this->myProcessedHistory($request);

        $tabs = [
            'needs-process' => [
                'label' => 'Perlu Saya Proses',
                'count' => count($myTasks),
            ],
            'processed-history' => [
                'label' => 'Riwayat yang Saya Proses',
                'count' => count($myProcessedHistory),
            ],
        ];
        $requestedTab = (string) $request->query('tab', '');
        $activeTab = array_key_exists($requestedTab, $tabs) ? $requestedTab : 'needs-process';
        $activeDocuments = match ($activeTab) {
            'processed-history' => $myProcessedHistory,
            default => $myTasks,
        };

        $filteredMyTasks = $this->filterDocuments($myTasks, $filters, 'date');
        $filteredMyProcessedHistory = $this->filterDocuments($myProcessedHistory, $filters, 'updated_at');

        return view('document-management.inbox', [
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'filters' => $filters,
            'typeOptions' => $this->optionsFrom($activeDocuments, 'type', 'Semua Jenis'),
            'statusOptions' => $this->optionsFrom($activeDocuments, 'status', 'Semua Status'),
            'stageOptions' => $this->optionsFrom($activeDocuments, 'stage', 'Semua Tahap'),
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
            ],
            'filteredMyTasks' => $filteredMyTasks,
            'filteredMyProcessedHistory' => $filteredMyProcessedHistory,
            'activeResultCount' => match ($activeTab) {
                'processed-history' => $filteredMyProcessedHistory->count(),
                default => $filteredMyTasks->count(),
            },
        ]);
    }

    /**
     * @return array{needs_process: int, processed_history: int}
     */
    public function dashboardCounts(Request $request): array
    {
        return [
            'needs_process' => count($this->myTasks($request)),
            'processed_history' => count($this->myProcessedHistory($request)),
        ];
    }

    /**
     * @return array{search: string, type: string, status: string, stage: string, sort: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
            'stage' => (string) $request->query('stage', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];
    }

    private function optionsFrom(array $documents, string $key, string $defaultLabel): array
    {
        return ['' => $defaultLabel] + collect($documents)
            ->pluck($key)
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($value) => [$value => $value])
            ->all();
    }

    private function filterDocuments(array $documents, array $filters, string $dateKey): Collection
    {
        return collect($documents)
            ->filter(function (array $document) use ($filters): bool {
                $haystack = strtolower(implode(' ', [
                    $document['number'],
                    $document['name'],
                    $document['type'],
                    $document['stage'],
                    $document['waiting_for'],
                    $document['status'],
                    $document['owner'] ?? '',
                    $document['department'] ?? '',
                ]));

                return ($filters['search'] === '' || str_contains($haystack, strtolower($filters['search'])))
                    && ($filters['type'] === '' || $document['type'] === $filters['type'])
                    && ($filters['status'] === '' || $document['status'] === $filters['status'])
                    && ($filters['stage'] === '' || $document['stage'] === $filters['stage']);
            })
            ->sortBy(function (array $document) use ($filters, $dateKey): mixed {
                return match ($filters['sort']) {
                    'oldest', 'newest' => $document[$dateKey.'_sort'] ?? $document[$dateKey],
                    'name_desc', 'name_asc' => $document['name'],
                    default => $document[$dateKey.'_sort'] ?? $document[$dateKey],
                };
            }, SORT_NATURAL, in_array($filters['sort'], ['newest', 'name_desc'], true))
            ->values();
    }

    private function myTasks(Request $request): array
    {
        $approvalScope = $this->approvalScope($request, processed: false);
        $assignedMonitorApprovalScope = $this->assignedMonitorApprovalScope($request);
        $assignedMonitorDocumentScope = $this->assignedMonitorDocumentScope($request);
        $assignableDocumentScope = $this->assignableDocumentScope($request);
        $rejectedCorrectionScope = $this->rejectedCorrectionScope($request);
        $pendingRevisionOwnerScope = $this->pendingRevisionOwnerScope($request);

        $query = Document::query()
            ->withExists([
                'approvals as has_flow_approvals' => function ($query): void {
                    $query->where('stages', '!=', 'TTD Penyusun Resmi');
                },
            ])
            ->with([
                'documentLevel',
                'documentType',
                'creator',
                'status',
                'departments',
                'revisedFrom.documentLevel',
                'approvals' => function ($query) use ($approvalScope, $assignedMonitorApprovalScope): void {
                    $query->where(function ($query) use ($approvalScope, $assignedMonitorApprovalScope): void {
                        $query
                            ->where($approvalScope)
                            ->orWhere($assignedMonitorApprovalScope);
                    });
                    $query->with(['status', 'approver'])->orderByDesc('assigned_at');
                },
            ]);

        $query->where(function ($query) use ($approvalScope, $assignedMonitorDocumentScope, $assignableDocumentScope, $rejectedCorrectionScope, $pendingRevisionOwnerScope): void {
            $query->whereHas('approvals', $approvalScope);
            $query->orWhere($assignedMonitorDocumentScope);

            if ($assignableDocumentScope !== null) {
                $query
                    ->orWhere($assignableDocumentScope);
            }

            $query->orWhere($rejectedCorrectionScope);
            $query->orWhere($pendingRevisionOwnerScope);
        });

        return $query->get()
            ->each(function (Document $document) use ($request): void {
                if (! $this->isPendingRequestOwnerTask($document, $request->user())) {
                    return;
                }

                $document->setRelation('approvals', $document->approvals()
                    ->where('stages', '!=', 'TTD Penyusun Resmi')
                    ->with(['status', 'approver'])
                    ->orderByRaw('responded_at is not null')
                    ->orderByRaw('case when m_approval_status_id = ? then 0 else 1 end', [
                        ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
                    ])
                    ->orderBy('assigned_at')
                    ->get());
            })
            ->filter(function (Document $document) use ($request): bool {
                if ($document->status?->nama_status === StatusDocument::REJECTED) {
                    return true;
                }

                return $this->isPendingRequestOwnerTask($document, $request->user())
                    || $this->isAssignedMonitorTask($document, $request->user())
                    || $document->approvals->first() !== null
                    || ! $document->has_flow_approvals;
            })
            ->map(fn (Document $document): array => $this->approvalRow(
                $document,
                $this->taskApproval($document, $request->user()),
                $request->user()->isAdmin() || $request->user()->canAssignDocument($document),
                $request->user(),
            ))
            ->all();
    }

    private function myProcessedHistory(Request $request): array
    {
        $approvalScope = $this->approvalScope($request, processed: true, includeAllForDeveloper: false);
        $assignedApprovalScope = $this->assignedApprovalScope($request);
        $user = $request->user();

        return Document::query()
            ->with([
                'documentLevel',
                'documentType',
                'creator',
                'status',
                'departments',
                'revisedFrom.documentLevel',
                'approvals' => function ($query) use ($approvalScope, $assignedApprovalScope): void {
                    $query->where(function ($query) use ($approvalScope, $assignedApprovalScope): void {
                        $query->where($approvalScope)
                            ->orWhere($assignedApprovalScope);
                    });
                    $query->with(['status', 'approver'])->orderByDesc('responded_at');
                },
            ])
            ->where(function ($query) use ($approvalScope, $assignedApprovalScope, $user): void {
                $query->whereHas('approvals', $approvalScope);
                $query->orWhereHas('approvals', $assignedApprovalScope);

                if (! $user->isDeveloper()) {
                    $query
                        ->orWhere(function ($query) use ($user): void {
                            $query
                                ->whereDoesntHave('status', fn ($query) => $query->where('nama_status', StatusDocument::REJECTED))
                                ->where(function ($query): void {
                                    $query
                                        ->whereNull('revised_from')
                                        ->orWhereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::APPROVED));
                                })
                                ->where(function ($query) use ($user): void {
                                    $query
                                        ->where('user_id', $user->id)
                                        ->orWhere('official_preparer_id', $user->id);
                                });
                        });
                }
            })
            ->get()
            ->reject(fn (Document $document): bool => $this->isPendingRequestOwnerTask($document, $user)
                || $this->isAssignedMonitorTask($document, $user))
            ->map(fn (Document $document): array => $this->processedHistoryRow(
                $document,
                $this->processedHistoryApproval($document, $user),
                $user,
            ))
            ->all();
    }

    private function processedHistoryApproval(Document $document, User $user): ?Approval
    {
        $respondedApproval = $document->approvals->first(
            fn (Approval $approval): bool => $approval->user_id === $user->id
                && $approval->responded_at !== null
                && $approval->stages !== 'TTD Penyusun Resmi',
        );

        if ($respondedApproval !== null) {
            return $respondedApproval;
        }

        $assignedApproval = $document->approvals->first(
            fn (Approval $approval): bool => $approval->assigned_by === $user->id
                && $approval->stages !== 'TTD Penyusun Resmi',
        );

        if ($assignedApproval !== null) {
            return $assignedApproval;
        }

        if ($document->user_id === $user->id) {
            return null;
        }

        return $document->approvals->first();
    }

    private function approvalScope(Request $request, bool $processed, bool $includeAllForDeveloper = true): callable
    {
        return function ($query) use ($request, $processed, $includeAllForDeveloper) {
            $query
                ->when(
                    ! $includeAllForDeveloper || ! $request->user()->isDeveloper(),
                    fn ($query) => $query->where('user_id', $request->user()->id),
                )
                ->when(
                    $processed,
                    fn ($query) => $query->whereNotNull('responded_at'),
                    fn ($query) => $query
                        ->whereNull('responded_at')
                        ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING)),
                );
        };
    }

    private function assignedMonitorApprovalScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->where('assigned_by', $request->user()->id)
                ->where('stages', '!=', 'TTD Penyusun Resmi')
                ->whereNull('responded_at')
                ->whereHas('status', fn ($query) => $query->whereIn('kode_status', [
                    ApprovalStatus::PENDING,
                    ApprovalStatus::WAITING,
                ]));
        };
    }

    private function assignedMonitorDocumentScope(Request $request): callable
    {
        $assignedMonitorApprovalScope = $this->assignedMonitorApprovalScope($request);

        return function ($query) use ($assignedMonitorApprovalScope): void {
            $query
                ->whereDoesntHave('status', fn ($query) => $query->whereIn('nama_status', [
                    StatusDocument::APPROVED,
                    StatusDocument::OBSOLETE,
                    StatusDocument::REJECTED,
                    StatusDocument::CANCELLED,
                ]))
                ->whereHas('approvals', $assignedMonitorApprovalScope);
        };
    }

    private function assignedApprovalScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->where('assigned_by', $request->user()->id)
                ->where('stages', '!=', 'TTD Penyusun Resmi');
        };
    }

    private function rejectedCorrectionScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::REJECTED))
                ->where(function ($query) use ($request): void {
                    $query
                        ->where('user_id', $request->user()->id)
                        ->orWhere('official_preparer_id', $request->user()->id);
                });
        };
    }

    private function pendingRevisionOwnerScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->whereNotNull('revised_from')
                ->whereHas('status', fn ($query) => $query->whereNotIn('nama_status', [
                    StatusDocument::APPROVED,
                    StatusDocument::OBSOLETE,
                    StatusDocument::REJECTED,
                    StatusDocument::CANCELLED,
                ]))
                ->where(function ($query) use ($request): void {
                    $query
                        ->where('user_id', $request->user()->id)
                        ->orWhere('official_preparer_id', $request->user()->id);
                });
        };
    }

    private function assignableDocumentScope(Request $request): ?callable
    {
        $user = $request->user();

        if ($user->isDeveloper() || $user->hasExplicitPermission('documents.approval.assign')) {
            return function ($query): void {
                $query->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED));
            };
        }

        if (! $user->isAdmin() && (! $user->isDocumentControlAdmin() || $user->m_department_id === null)) {
            return null;
        }

        return function ($query) use ($user): void {
            $query->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED));
            $query->whereDoesntHave('approvals', function ($query): void {
                $query->where('stages', '!=', 'TTD Penyusun Resmi');
            });

            if (! $user->isAdmin()) {
                $query->whereHas('departments', fn ($query) => $query->whereKey($user->m_department_id));
            }
        };
    }

    private function approvalRow(Document $document, ?Approval $approval, bool $canAssign = false, ?User $user = null): array
    {
        $assignedAt = $approval?->assigned_at;
        $respondedAt = $approval?->responded_at;
        $submittedAt = $document->submitted_at ?? $document->created_at;
        $isRejectedCorrection = $document->status?->nama_status === StatusDocument::REJECTED && $approval === null;
        $isPendingRequestOwnerDocument = $user !== null && $this->isPendingRequestOwnerTask($document, $user);
        $isPendingRequestOwnerTask = $isPendingRequestOwnerDocument && $approval === null && ! $canAssign;
        $isRequestOwnerActiveStageTask = $isPendingRequestOwnerDocument
            && $approval !== null
            && $approval->user_id !== $user->id
            && ! $canAssign;
        $pendingOwnerApproval = $isPendingRequestOwnerTask ? $this->pendingOwnerApproval($document) : null;
        $isAssignableDocumentTask = $approval === null
            && $canAssign
            && $document->status?->nama_status === StatusDocument::PROPOSED;
        $isAssignedMonitorTask = $user !== null && $approval !== null && $this->isAssignedMonitorApproval($approval, $user);
        $statusCode = $approval?->status?->kode_status
            ?? $approval?->status?->nama_status
            ?? $document->status?->nama_status
            ?? '-';
        $displayStatus = $this->documentDisplayStatus(
            $document,
            $approval,
            $isPendingRequestOwnerTask,
            $isAssignableDocumentTask,
            $isRequestOwnerActiveStageTask,
        );
        $displayStatusCode = $isPendingRequestOwnerTask || $isAssignableDocumentTask || $isRequestOwnerActiveStageTask
            ? ApprovalStatus::PENDING
            : $statusCode;

        return [
            'id' => $document->id,
            'detail_url' => route('documents.approval.show', $document),
            'number' => $this->documentDisplayNumber($document),
            'name' => $document->nama_dokumen ?? '-',
            'type' => $this->documentTypeLabel($document),
            'type_tone' => $document->request_type === 'obsolete' ? 'red' : null,
            'stage' => match (true) {
                $isRejectedCorrection => 'Perbaikan Pengajuan',
                $isPendingRequestOwnerTask => $document->request_type === 'obsolete' ? 'Pengajuan Obsolete' : 'Pengajuan Revisi',
                default => $approval?->stages ?: ($canAssign ? 'Belum assign approver' : 'Approval'),
            },
            'waiting_for' => $approval?->approver?->name ?? ($canAssign || $isPendingRequestOwnerTask ? 'Admin Kontrol Dokumen' : '-'),
            'owner' => $document->creator?->name ?? '-',
            'department' => $document->departments
                ->map(fn ($department) => $department->kode_department ?: $department->nama_department)
                ->filter()
                ->implode(', ') ?: '-',
            'date' => $assignedAt?->translatedFormat('d M Y H:i:s') ?? $submittedAt?->translatedFormat('d M Y H:i:s') ?? '-',
            'date_sort' => $assignedAt?->timestamp ?? $submittedAt?->timestamp ?? 0,
            'submitted_at' => $submittedAt?->translatedFormat('d M Y') ?? '-',
            'submitted_at_sort' => $submittedAt?->timestamp ?? 0,
            'updated_at' => $respondedAt?->translatedFormat('d M Y H:i') ?? '-',
            'updated_at_sort' => $respondedAt?->timestamp ?? 0,
            'status' => $displayStatus,
            'tone' => $this->approvalTone($displayStatusCode),
            'action' => match (true) {
                $isRejectedCorrection => 'Perlu Perbaikan',
                $isAssignedMonitorTask => $this->waitingActionLabel($approval),
                $approval !== null => $this->waitingActionLabel($approval),
                $isPendingRequestOwnerTask => $pendingOwnerApproval !== null
                    ? $this->waitingActionLabel($pendingOwnerApproval)
                    : 'Perlu Verifikasi Admin KD',
                default => 'Perlu Verifikasi Admin KD',
            },
        ];
    }

    private function isPendingRequestOwnerTask(Document $document, User $user): bool
    {
        return $document->revised_from !== null
            && in_array($document->status?->nama_status, [
                StatusDocument::DRAFT,
                StatusDocument::PROPOSED,
            ], true)
            && in_array($user->id, [$document->user_id, $document->official_preparer_id], true);
    }

    private function pendingOwnerApproval(Document $document): ?Approval
    {
        return $document->approvals
            ->first(fn (Approval $approval): bool => $approval->responded_at === null
                && $approval->status?->kode_status === ApprovalStatus::PENDING
                && $approval->stages !== 'TTD Penyusun Resmi')
            ?? $document->approvals
                ->first(fn (Approval $approval): bool => $approval->responded_at === null
                    && $approval->stages !== 'TTD Penyusun Resmi');
    }

    private function taskApproval(Document $document, User $user): ?Approval
    {
        $monitorApproval = $document->approvals
            ->filter(fn (Approval $approval): bool => $this->isAssignedMonitorApproval($approval, $user))
            ->sortBy(fn (Approval $approval): int => $approval->status?->kode_status === ApprovalStatus::PENDING ? 0 : 1)
            ->first();

        if ($monitorApproval !== null) {
            return $monitorApproval;
        }

        return $document->approvals->first();
    }

    private function isAssignedMonitorTask(Document $document, User $user): bool
    {
        if (in_array($document->status?->nama_status, [
            StatusDocument::APPROVED,
            StatusDocument::OBSOLETE,
            StatusDocument::REJECTED,
            StatusDocument::CANCELLED,
        ], true)) {
            return false;
        }

        return $document->approvals->contains(
            fn (Approval $approval): bool => $this->isAssignedMonitorApproval($approval, $user),
        );
    }

    private function isAssignedMonitorApproval(Approval $approval, User $user): bool
    {
        return $approval->assigned_by === $user->id
            && $approval->user_id !== $user->id
            && $approval->stages !== 'TTD Penyusun Resmi'
            && $approval->responded_at === null
            && in_array($approval->status?->kode_status, [
                ApprovalStatus::PENDING,
                ApprovalStatus::WAITING,
            ], true);
    }

    private function waitingActionLabel(Approval $approval): string
    {
        $stage = trim($approval->stages ?: 'approver');

        if (preg_match('/\boleh\s+(.+)$/i', $stage, $matches) === 1) {
            $stage = trim($matches[1]);
        }

        if (preg_match('/^approval\s+(.+)$/i', $stage, $matches) === 1) {
            $stage = trim($matches[1]);
        }

        return 'Menunggu '.$stage;
    }

    private function processedHistoryRow(Document $document, ?Approval $approval, User $user): array
    {
        if ($approval !== null) {
            if ($approval->assigned_by === $user->id && $approval->user_id !== $user->id) {
                return $this->assignedHistoryRow($document, $approval);
            }

            return $this->approvalRow($document, $approval, $user->isDeveloper());
        }

        $row = $this->approvalRow($document, null);
        $submittedAt = $document->submitted_at ?? $document->created_at;

        $row['stage'] = match (true) {
            $document->user_id === $user->id && $document->revised_from !== null => 'Pengajuan Revisi',
            $document->user_id === $user->id => 'Pengajuan Dokumen',
            $document->official_preparer_id === $user->id => 'TTD Penyusun Resmi',
            default => 'Pengajuan Dokumen',
        };
        $row['waiting_for'] = '-';
        $row['updated_at'] = $submittedAt?->translatedFormat('d M Y H:i') ?? '-';
        $row['updated_at_sort'] = $submittedAt?->timestamp ?? 0;
        $row['action'] = 'Lihat';

        return $row;
    }

    private function assignedHistoryRow(Document $document, Approval $approval): array
    {
        $row = $this->approvalRow($document, null);

        $row['stage'] = 'Assign Approver';
        $row['waiting_for'] = $approval->approver?->name ?? '-';
        $row['updated_at'] = $approval->assigned_at?->translatedFormat('d M Y H:i') ?? '-';
        $row['updated_at_sort'] = $approval->assigned_at?->timestamp ?? 0;
        $row['action'] = 'Lihat';

        return $row;
    }

    private function documentTypeLabel(Document $document): string
    {
        if ($document->request_type === 'obsolete') {
            return 'Obsolete';
        }

        return match ($document->documentType?->nama_types) {
            'IK' => 'Instruksi Kerja',
            default => $document->documentType?->nama_types ?? '-',
        };
    }

    private function documentDisplayStatus(
        Document $document,
        ?Approval $approval,
        bool $isPendingRequestOwnerTask,
        bool $isAssignableDocumentTask = false,
        bool $isRequestOwnerActiveStageTask = false,
    ): string
    {
        if ($isPendingRequestOwnerTask && in_array($document->status?->nama_status, [
            StatusDocument::DRAFT,
            StatusDocument::PROPOSED,
        ], true)) {
            return 'Dalam Review';
        }

        if ($isAssignableDocumentTask) {
            return 'Dalam Review';
        }

        if ($isRequestOwnerActiveStageTask) {
            return 'Dalam Review';
        }

        return $approval?->status?->nama_status
            ?? $document->status?->nama_status
            ?? '-';
    }

    private function documentDisplayNumber(Document $document): string
    {
        if ($document->request_type === 'obsolete') {
            return $this->masterRequestNumber($document)
                ?? $document->nomor_dokumen
                ?? '-';
        }

        return $this->revisionRequestNumber($document)
            ?? $document->nomor_dokumen
            ?? '-';
    }

    private function masterRequestNumber(Document $document): ?string
    {
        if ($document->revised_from === null) {
            return $document->nomor_dokumen;
        }

        return Document::query()
            ->whereKey($document->revisionRootId())
            ->value('nomor_dokumen');
    }

    private function revisionRequestNumber(Document $document): ?string
    {
        if ($document->request_type !== 'revision' || $document->revised_from === null) {
            return null;
        }

        if ($document->documentLevel?->kode === 'level-4' && str_starts_with((string) $document->nomor_dokumen, 'FM')) {
            return $document->nomor_dokumen;
        }

        $source = $document->revisedFrom;

        if ($source === null) {
            return null;
        }

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
            ->implode('-');
    }

    private function approvalTone(string $statusCode): string
    {
        return match ($statusCode) {
            ApprovalStatus::APPROVED => 'emerald',
            ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED => 'rose',
            default => 'sky',
        };
    }
}
