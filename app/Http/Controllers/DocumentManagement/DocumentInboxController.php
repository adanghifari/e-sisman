<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalStatus;
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
        return Approval::query()
            ->with(['status', 'approver', 'document.documentType', 'document.creator', 'document.departments'])
            ->where('user_id', $request->user()->id)
            ->whereNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
            ->orderByDesc('assigned_at')
            ->get()
            ->map(fn (Approval $approval): array => $this->approvalRow($approval))
            ->all();
    }

    private function myProcessedHistory(Request $request): array
    {
        return Approval::query()
            ->with(['status', 'approver', 'document.documentType', 'document.creator', 'document.departments'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('responded_at')
            ->orderByDesc('responded_at')
            ->get()
            ->map(fn (Approval $approval): array => $this->approvalRow($approval))
            ->all();
    }

    private function approvalRow(Approval $approval): array
    {
        $document = $approval->document;
        $assignedAt = $approval->assigned_at;
        $respondedAt = $approval->responded_at;
        $submittedAt = $document?->submitted_at ?? $document?->created_at;
        $statusCode = $approval->status?->kode_status ?? $approval->status?->nama_status ?? '-';

        return [
            'number' => $document?->nomor_dokumen ?? '-',
            'name' => $document?->nama_dokumen ?? '-',
            'type' => $document?->documentType?->nama_types ?? '-',
            'stage' => $approval->stages ?: 'Approval',
            'waiting_for' => $approval->approver?->name ?? '-',
            'owner' => $document?->creator?->name ?? '-',
            'department' => $document?->departments
                ->map(fn ($department) => $department->kode_department ?: $department->nama_department)
                ->filter()
                ->implode(', ') ?: '-',
            'date' => $assignedAt?->translatedFormat('d M Y') ?? '-',
            'date_sort' => $assignedAt?->timestamp ?? 0,
            'submitted_at' => $submittedAt?->translatedFormat('d M Y') ?? '-',
            'submitted_at_sort' => $submittedAt?->timestamp ?? 0,
            'updated_at' => $respondedAt?->translatedFormat('d M Y H:i') ?? '-',
            'updated_at_sort' => $respondedAt?->timestamp ?? 0,
            'status' => $approval->status?->nama_status ?? $statusCode,
            'tone' => $this->approvalTone($statusCode),
            'action' => 'Proses',
        ];
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
