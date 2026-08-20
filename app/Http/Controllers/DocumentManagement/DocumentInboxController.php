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
        $assignableDocumentScope = $this->assignableDocumentScope($request);

        $query = Document::query()
            ->withExists([
                'approvals as has_flow_approvals' => function ($query): void {
                    $query->where('stages', '!=', 'TTD Penyusun Resmi');
                },
            ])
            ->with([
                'documentType',
                'creator',
                'status',
                'departments',
                'approvals' => function ($query) use ($approvalScope): void {
                    $approvalScope($query);
                    $query->with(['status', 'approver'])->orderByDesc('assigned_at');
                },
            ]);

        $query->where(function ($query) use ($approvalScope, $assignableDocumentScope): void {
            $query->whereHas('approvals', $approvalScope);

            if ($assignableDocumentScope !== null) {
                $query
                    ->orWhere($assignableDocumentScope);
            }
        });

        return $query->get()
            ->filter(function (Document $document): bool {
                return $document->approvals->first() !== null || ! $document->has_flow_approvals;
            })
            ->map(fn (Document $document): array => $this->approvalRow(
                $document,
                $document->approvals->first(),
                $request->user()->isAdmin() || $request->user()->canAssignDocument($document),
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
                'documentType',
                'creator',
                'status',
                'departments',
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
                        ->orWhere('user_id', $user->id)
                        ->orWhere('official_preparer_id', $user->id);
                }
            })
            ->get()
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

    private function assignedApprovalScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->where('assigned_by', $request->user()->id)
                ->where('stages', '!=', 'TTD Penyusun Resmi');
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

    private function approvalRow(Document $document, ?Approval $approval, bool $canAssign = false): array
    {
        $assignedAt = $approval?->assigned_at;
        $respondedAt = $approval?->responded_at;
        $submittedAt = $document->submitted_at ?? $document->created_at;
        $statusCode = $approval?->status?->kode_status
            ?? $approval?->status?->nama_status
            ?? $document->status?->nama_status
            ?? '-';

        return [
            'id' => $document->id,
            'detail_url' => route('documents.approval.show', $document),
            'number' => $document->nomor_dokumen ?? '-',
            'name' => $document->nama_dokumen ?? '-',
            'type' => $document->documentType?->nama_types ?? '-',
            'stage' => $approval?->stages ?: ($canAssign ? 'Belum assign approver' : 'Approval'),
            'waiting_for' => $approval?->approver?->name ?? ($canAssign ? 'Admin Kontrol Dokumen' : '-'),
            'owner' => $document->creator?->name ?? '-',
            'department' => $document->departments
                ->map(fn ($department) => $department->kode_department ?: $department->nama_department)
                ->filter()
                ->implode(', ') ?: '-',
            'date' => $assignedAt?->translatedFormat('d M Y') ?? $submittedAt?->translatedFormat('d M Y') ?? '-',
            'date_sort' => $assignedAt?->timestamp ?? $submittedAt?->timestamp ?? 0,
            'submitted_at' => $submittedAt?->translatedFormat('d M Y') ?? '-',
            'submitted_at_sort' => $submittedAt?->timestamp ?? 0,
            'updated_at' => $respondedAt?->translatedFormat('d M Y H:i') ?? '-',
            'updated_at_sort' => $respondedAt?->timestamp ?? 0,
            'status' => $approval?->status?->nama_status ?? $document->status?->nama_status ?? $statusCode,
            'tone' => $this->approvalTone($statusCode),
            'action' => $approval ? 'Proses' : 'Assign',
        ];
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

    private function approvalTone(string $statusCode): string
    {
        return match ($statusCode) {
            ApprovalStatus::APPROVED => 'emerald',
            ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED => 'rose',
            default => 'sky',
        };
    }
}
