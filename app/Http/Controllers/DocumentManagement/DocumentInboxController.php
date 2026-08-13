<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DocumentInboxController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $this->filters($request);
        $needsProcess = $this->needsProcessDocuments();
        $myTasks = $this->myTasks($request);
        $mySubmissionHistory = $this->mySubmissionHistory($request);

        $tabs = [
            'needs-process' => [
                'label' => 'Semua Dokumen Butuh Diproses',
                'count' => count($needsProcess),
            ],
            'my-tasks' => [
                'label' => 'Perlu Saya Proses',
                'count' => count($myTasks),
            ],
            'my-history' => [
                'label' => 'Riwayat Pengajuan Saya',
                'count' => count($mySubmissionHistory),
            ],
        ];
        $requestedTab = (string) $request->query('tab', '');
        $activeTab = array_key_exists($requestedTab, $tabs) ? $requestedTab : 'needs-process';
        $activeDocuments = match ($activeTab) {
            'my-tasks' => $myTasks,
            'my-history' => $mySubmissionHistory,
            default => $needsProcess,
        };

        $filteredNeedsProcess = $this->filterDocuments($needsProcess, $filters, 'date');
        $filteredMyTasks = $this->filterDocuments($myTasks, $filters, 'date');
        $filteredMySubmissionHistory = $this->filterDocuments($mySubmissionHistory, $filters, 'submitted_at');

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
            'filteredNeedsProcess' => $filteredNeedsProcess,
            'filteredMyTasks' => $filteredMyTasks,
            'filteredMySubmissionHistory' => $filteredMySubmissionHistory,
            'activeResultCount' => match ($activeTab) {
                'my-tasks' => $filteredMyTasks->count(),
                'my-history' => $filteredMySubmissionHistory->count(),
                default => $filteredNeedsProcess->count(),
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

    private function needsProcessDocuments(): array
    {
        $statusLabels = [
            StatusDocument::PROPOSED => ['label' => 'Diajukan', 'tone' => 'sky', 'stage' => 'Pengajuan Dokumen', 'waiting_for' => 'Admin Kontrol Dokumen'],
            StatusDocument::REJECTED => ['label' => 'Perlu Koreksi', 'tone' => 'amber', 'stage' => 'Koreksi Pengaju', 'waiting_for' => null],
        ];
        $excludedProcessStatuses = [
            StatusDocument::APPROVED,
            StatusDocument::CANCELLED,
            StatusDocument::OBSOLETE,
        ];

        return Document::query()
            ->with(['status', 'documentType', 'creator', 'officialPreparer', 'departments'])
            ->whereNotNull('submitted_at')
            ->whereHas('status', fn ($query) => $query->whereNotIn('nama_status', $excludedProcessStatuses))
            ->latest('submitted_at')
            ->get()
            ->map(function (Document $document) use ($statusLabels): array {
                $statusName = $document->status?->nama_status ?? '-';
                $statusMeta = $statusLabels[$statusName] ?? [
                    'label' => $statusName,
                    'tone' => 'sky',
                    'stage' => 'Dalam Proses',
                    'waiting_for' => 'Approver Berikutnya',
                ];
                $submittedAt = $document->submitted_at ?? $document->created_at;

                return [
                    'number' => $document->nomor_dokumen ?? '-',
                    'name' => $document->nama_dokumen,
                    'type' => $document->documentType?->nama_types ?? '-',
                    'stage' => $statusMeta['stage'],
                    'waiting_for' => $statusMeta['waiting_for'] ?? $document->creator?->name ?? '-',
                    'owner' => $document->creator?->name ?? '-',
                    'official_preparer' => $document->officialPreparer?->name ?? '-',
                    'department' => $document->departments
                        ->map(fn ($department) => $department->kode_department ?: $department->nama_department)
                        ->filter()
                        ->implode(', ') ?: '-',
                    'date' => $submittedAt?->translatedFormat('d M Y') ?? '-',
                    'date_sort' => $submittedAt?->timestamp ?? 0,
                    'status' => $statusMeta['label'],
                    'tone' => $statusMeta['tone'],
                ];
            })
            ->all();
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
        return [
            ['number' => 'KBS-PB-PR-001', 'name' => 'Prosedur Pengendalian Dokumen', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => $request->user()->name, 'owner' => 'Rendy Aulia', 'department' => 'PB', 'date' => '10 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
            ['number' => 'KBS-QA-FM-011', 'name' => 'Form Checklist Audit Mutu Internal', 'type' => 'Form', 'stage' => 'Verifikasi Admin', 'waiting_for' => $request->user()->name, 'owner' => 'Siska Amelia', 'department' => 'QA', 'date' => '08 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
            ['number' => 'KBS-PB-IK-010', 'name' => 'IK Pengelolaan Template Dokumen', 'type' => 'IK', 'stage' => 'Koreksi Pengaju', 'waiting_for' => $request->user()->name, 'owner' => $request->user()->name, 'department' => 'PB', 'date' => '09 Agu 2026', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'action' => 'Perbaiki'],
            ['number' => 'KBS-OPS-FM-006', 'name' => 'Form Checklist Inspeksi Harian', 'type' => 'Form', 'stage' => 'Koreksi Pengaju', 'waiting_for' => $request->user()->name, 'owner' => $request->user()->name, 'department' => 'OPS', 'date' => '06 Agu 2026', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'action' => 'Perbaiki'],
            ['number' => 'KBS-PB-PR-013', 'name' => 'Prosedur Distribusi Dokumen Terkendali', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => $request->user()->name, 'owner' => 'Ayu Lestari', 'department' => 'PB', 'date' => '05 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
        ];
    }

    private function mySubmissionHistory(Request $request): array
    {
        return [
            ['number' => 'KBS-AUD-PR-002', 'name' => 'Revisi Prosedur Audit Internal', 'type' => 'Prosedur', 'submitted_at' => '10 Agu 2026', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager QA', 'updated_at' => '10 Agu 2026 09:55', 'status' => 'Dalam Approval', 'tone' => 'sky'],
            ['number' => 'KBS-PB-IK-010', 'name' => 'IK Pengelolaan Template Dokumen', 'type' => 'IK', 'submitted_at' => '09 Agu 2026', 'stage' => 'Koreksi Pengaju', 'waiting_for' => $request->user()->name, 'updated_at' => '10 Agu 2026 10:42', 'status' => 'Perlu Koreksi', 'tone' => 'amber'],
            ['number' => 'KBS-DM-PR-007', 'name' => 'Prosedur Penerbitan Dokumen Master', 'type' => 'Prosedur', 'submitted_at' => '08 Agu 2026', 'stage' => 'Publish', 'waiting_for' => 'Admin Dokumen Master', 'updated_at' => '09 Agu 2026 14:30', 'status' => 'Approved', 'tone' => 'emerald'],
            ['number' => 'KBS-HSE-IK-021', 'name' => 'Revisi IK Pemeriksaan Alat Angkat', 'type' => 'IK', 'submitted_at' => '07 Agu 2026', 'stage' => 'Review Kadis', 'waiting_for' => 'Kadis HSE', 'updated_at' => '08 Agu 2026 11:05', 'status' => 'Dalam Approval', 'tone' => 'sky'],
            ['number' => 'KBS-OPS-FM-006', 'name' => 'Form Checklist Inspeksi Harian', 'type' => 'Form', 'submitted_at' => '06 Agu 2026', 'stage' => 'Koreksi Pengaju', 'waiting_for' => $request->user()->name, 'updated_at' => '07 Agu 2026 15:12', 'status' => 'Perlu Koreksi', 'tone' => 'amber'],
            ['number' => 'KBS-RSK-PR-004', 'name' => 'Prosedur Pengelolaan Risiko Operasional', 'type' => 'Prosedur', 'submitted_at' => '05 Agu 2026', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager Risiko', 'updated_at' => '06 Agu 2026 13:20', 'status' => 'Dalam Approval', 'tone' => 'sky'],
        ];
    }
}
