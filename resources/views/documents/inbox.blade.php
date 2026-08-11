<x-layouts::app :title="__('Dokumen Butuh Diproses')">
    @php
        $tabs = [
            'needs-process' => [
                'label' => 'Semua Dokumen Butuh Diproses',
                'count' => 10,
            ],
            'my-tasks' => [
                'label' => 'Perlu Saya Proses',
                'count' => 5,
            ],
            'my-history' => [
                'label' => 'Riwayat Pengajuan Saya',
                'count' => 6,
            ],
        ];
        $activeTab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'needs-process';
        $filters = [
            'search' => trim((string) request('search', '')),
            'type' => (string) request('type', ''),
            'status' => (string) request('status', ''),
            'stage' => (string) request('stage', ''),
            'sort' => (string) request('sort', 'newest'),
        ];

        $needsProcess = [
            ['number' => 'KBS-PB-PR-001', 'name' => 'Prosedur Pengendalian Dokumen', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'owner' => 'Rendy Aulia', 'department' => 'PB', 'date' => '10 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky'],
            ['number' => 'KBS-OPS-IK-014', 'name' => 'Instruksi Kerja Bongkar Muat Curah', 'type' => 'IK', 'stage' => 'Review Kadis', 'waiting_for' => 'Kadis Operasional', 'owner' => 'Oktavia Putri', 'department' => 'OPS', 'date' => '09 Agu 2026', 'status' => 'Review', 'tone' => 'sky'],
            ['number' => 'KBS-HSE-IK-008', 'name' => 'Instruksi Kerja Inspeksi Area Dermaga', 'type' => 'IK', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager HSE', 'owner' => 'Aditya Chandra', 'department' => 'HSE', 'date' => '08 Agu 2026', 'status' => 'Approval', 'tone' => 'sky'],
            ['number' => 'KBS-QA-FM-011', 'name' => 'Form Checklist Audit Mutu Internal', 'type' => 'Form', 'stage' => 'Verifikasi Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'owner' => 'Siska Amelia', 'department' => 'QA', 'date' => '08 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky'],
            ['number' => 'KBS-HC-PR-004', 'name' => 'Prosedur Onboarding Karyawan Baru', 'type' => 'Prosedur', 'stage' => 'Review Kadis', 'waiting_for' => 'Kadis HC', 'owner' => 'Bima Pratama', 'department' => 'HC', 'date' => '07 Agu 2026', 'status' => 'Review', 'tone' => 'sky'],
            ['number' => 'KBS-FIN-IK-006', 'name' => 'IK Rekonsiliasi Pembayaran Vendor', 'type' => 'IK', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager Finance', 'owner' => 'Dian Kartika', 'department' => 'FIN', 'date' => '07 Agu 2026', 'status' => 'Approval', 'tone' => 'sky'],
            ['number' => 'KBS-OPS-PR-018', 'name' => 'Prosedur Penjadwalan Kapal Sandar', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'owner' => 'Naufal Rizky', 'department' => 'OPS', 'date' => '06 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky'],
            ['number' => 'KBS-HSE-FM-003', 'name' => 'Form Laporan Insiden Keselamatan', 'type' => 'Form', 'stage' => 'Review Kadis', 'waiting_for' => 'Kadis HSE', 'owner' => 'Maya Safitri', 'department' => 'HSE', 'date' => '06 Agu 2026', 'status' => 'Review', 'tone' => 'sky'],
            ['number' => 'KBS-IT-IK-009', 'name' => 'IK Backup Database Aplikasi Internal', 'type' => 'IK', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager IT', 'owner' => 'Fajar Nugroho', 'department' => 'IT', 'date' => '05 Agu 2026', 'status' => 'Approval', 'tone' => 'sky'],
            ['number' => 'KBS-PB-PR-013', 'name' => 'Prosedur Distribusi Dokumen Terkendali', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'owner' => 'Ayu Lestari', 'department' => 'PB', 'date' => '05 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky'],
        ];

        $myTasks = [
            ['number' => 'KBS-PB-PR-001', 'name' => 'Prosedur Pengendalian Dokumen', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => auth()->user()->name, 'owner' => 'Rendy Aulia', 'department' => 'PB', 'date' => '10 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
            ['number' => 'KBS-QA-FM-011', 'name' => 'Form Checklist Audit Mutu Internal', 'type' => 'Form', 'stage' => 'Verifikasi Admin', 'waiting_for' => auth()->user()->name, 'owner' => 'Siska Amelia', 'department' => 'QA', 'date' => '08 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
            ['number' => 'KBS-PB-IK-010', 'name' => 'IK Pengelolaan Template Dokumen', 'type' => 'IK', 'stage' => 'Koreksi Pengaju', 'waiting_for' => auth()->user()->name, 'owner' => auth()->user()->name, 'department' => 'PB', 'date' => '09 Agu 2026', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'action' => 'Perbaiki'],
            ['number' => 'KBS-OPS-FM-006', 'name' => 'Form Checklist Inspeksi Harian', 'type' => 'Form', 'stage' => 'Koreksi Pengaju', 'waiting_for' => auth()->user()->name, 'owner' => auth()->user()->name, 'department' => 'OPS', 'date' => '06 Agu 2026', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'action' => 'Perbaiki'],
            ['number' => 'KBS-PB-PR-013', 'name' => 'Prosedur Distribusi Dokumen Terkendali', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'waiting_for' => auth()->user()->name, 'owner' => 'Ayu Lestari', 'department' => 'PB', 'date' => '05 Agu 2026', 'status' => 'Menunggu', 'tone' => 'sky', 'action' => 'Verifikasi'],
        ];

        $mySubmissionHistory = [
            ['number' => 'KBS-AUD-PR-002', 'name' => 'Revisi Prosedur Audit Internal', 'type' => 'Prosedur', 'submitted_at' => '10 Agu 2026', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager QA', 'updated_at' => '10 Agu 2026 09:55', 'status' => 'Dalam Approval', 'tone' => 'sky'],
            ['number' => 'KBS-PB-IK-010', 'name' => 'IK Pengelolaan Template Dokumen', 'type' => 'IK', 'submitted_at' => '09 Agu 2026', 'stage' => 'Koreksi Pengaju', 'waiting_for' => auth()->user()->name, 'updated_at' => '10 Agu 2026 10:42', 'status' => 'Perlu Koreksi', 'tone' => 'amber'],
            ['number' => 'KBS-DM-PR-007', 'name' => 'Prosedur Penerbitan Dokumen Master', 'type' => 'Prosedur', 'submitted_at' => '08 Agu 2026', 'stage' => 'Publish', 'waiting_for' => 'Admin Dokumen Master', 'updated_at' => '09 Agu 2026 14:30', 'status' => 'Approved', 'tone' => 'emerald'],
            ['number' => 'KBS-HSE-IK-021', 'name' => 'Revisi IK Pemeriksaan Alat Angkat', 'type' => 'IK', 'submitted_at' => '07 Agu 2026', 'stage' => 'Review Kadis', 'waiting_for' => 'Kadis HSE', 'updated_at' => '08 Agu 2026 11:05', 'status' => 'Dalam Approval', 'tone' => 'sky'],
            ['number' => 'KBS-OPS-FM-006', 'name' => 'Form Checklist Inspeksi Harian', 'type' => 'Form', 'submitted_at' => '06 Agu 2026', 'stage' => 'Koreksi Pengaju', 'waiting_for' => auth()->user()->name, 'updated_at' => '07 Agu 2026 15:12', 'status' => 'Perlu Koreksi', 'tone' => 'amber'],
            ['number' => 'KBS-RSK-PR-004', 'name' => 'Prosedur Pengelolaan Risiko Operasional', 'type' => 'Prosedur', 'submitted_at' => '05 Agu 2026', 'stage' => 'Approval Manager', 'waiting_for' => 'Manager Risiko', 'updated_at' => '06 Agu 2026 13:20', 'status' => 'Dalam Approval', 'tone' => 'sky'],
        ];

        $activeDocuments = match ($activeTab) {
            'my-tasks' => $myTasks,
            'my-history' => $mySubmissionHistory,
            default => $needsProcess,
        };
        $typeOptions = ['' => 'Semua Jenis'] + collect($activeDocuments)->pluck('type')->unique()->sort()->mapWithKeys(fn ($type) => [$type => $type])->all();
        $statusOptions = ['' => 'Semua Status'] + collect($activeDocuments)->pluck('status')->unique()->sort()->mapWithKeys(fn ($status) => [$status => $status])->all();
        $stageOptions = ['' => 'Semua Tahap'] + collect($activeDocuments)->pluck('stage')->unique()->sort()->mapWithKeys(fn ($stage) => [$stage => $stage])->all();
        $sortOptions = [
            'newest' => 'Terbaru',
            'oldest' => 'Terlama',
            'name_asc' => 'Nama A-Z',
            'name_desc' => 'Nama Z-A',
        ];

        $filterDocuments = function (array $documents, string $dateKey) use ($filters) {
            return collect($documents)
                ->filter(function ($document) use ($filters) {
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
                ->sortBy(function ($document) use ($filters, $dateKey) {
                    return match ($filters['sort']) {
                        'oldest', 'newest' => $document[$dateKey],
                        'name_desc', 'name_asc' => $document['name'],
                        default => $document[$dateKey],
                    };
                }, SORT_NATURAL, in_array($filters['sort'], ['newest', 'name_desc'], true))
                ->values();
        };

        $filteredNeedsProcess = $filterDocuments($needsProcess, 'date');
        $filteredMyTasks = $filterDocuments($myTasks, 'date');
        $filteredMySubmissionHistory = $filterDocuments($mySubmissionHistory, 'submitted_at');
        $activeResultCount = match ($activeTab) {
            'my-tasks' => $filteredMyTasks->count(),
            'my-history' => $filteredMySubmissionHistory->count(),
            default => $filteredNeedsProcess->count(),
        };
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            title="Dokumen Butuh Diproses"
            description="Pantau dokumen yang masih berada dalam proses verifikasi, review, dan approval."
        />

        <x-ui.filter-bar :action="route('documents.inbox')">
            <x-slot:tabs>
                @foreach ($tabs as $key => $tab)
                    @php
                        $active = $activeTab === $key;
                    @endphp

                    <a
                        href="{{ route('documents.inbox', ['tab' => $key]) }}"
                        class="inline-flex min-h-10 items-center gap-2 rounded-md px-3 text-sm font-semibold transition {{ $active ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                        wire:navigate
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs {{ $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">
                            {{ $tab['count'] }}
                        </span>
                    </a>
                @endforeach
            </x-slot:tabs>

            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nomor, dokumen, pengaju, tahap..."
            />

            <x-ui.select label="Jenis" name="type" :value="$filters['type']" :options="$typeOptions" />
            <x-ui.select label="Status" name="status" :value="$filters['status']" :options="$statusOptions" />
            <x-ui.select label="Tahap" name="stage" :value="$filters['stage']" :options="$stageOptions" />
            <x-ui.select label="Urutkan" name="sort" :value="$filters['sort']" :options="$sortOptions" />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('documents.inbox', ['tab' => $activeTab]) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <div class="text-sm font-medium text-slate-500">
            Menampilkan {{ $activeResultCount }} dokumen
        </div>

        @if ($activeTab === 'needs-process')
            <x-ui.panel
                title="Semua Dokumen Butuh Diproses"
                description="Semua dokumen yang belum selesai disetujui seluruh approver dan masih berada di tahap proses."
                :padded="false"
            >
                <x-ui.scrollable-table max-height="560px" min-width="1120px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Menunggu</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dept</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tanggal</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($filteredNeedsProcess as $document)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $document['number'] }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['type'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['waiting_for'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['owner'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['department'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['date'] }}</td>
                                <td class="px-5 py-4">
                                    <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
                                </td>
                                <td class="px-5 py-4">
                                    <details class="group">
                                        <summary class="list-none rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Detail
                                        </summary>
                                        <div class="mt-2 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-sm">
                                            <p class="font-semibold text-slate-800">{{ $document['name'] }}</p>
                                            <p class="mt-2">Tahap: {{ $document['stage'] }}</p>
                                            <p class="mt-1">Menunggu: {{ $document['waiting_for'] }}</p>
                                            <p class="mt-1">Pengaju: {{ $document['owner'] }}</p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada dokumen yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.scrollable-table>
            </x-ui.panel>
        @endif

        @if ($activeTab === 'my-tasks')
            <x-ui.panel
                title="Perlu Saya Proses"
                description="Dokumen yang saat ini menunggu tindakan dari user login."
                :padded="false"
            >
                <x-ui.scrollable-table max-height="560px" min-width="1080px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dept</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tanggal</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tindakan</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($filteredMyTasks as $document)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $document['number'] }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['type'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['owner'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['department'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['date'] }}</td>
                                <td class="px-5 py-4">
                                    <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                        {{ $document['action'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <details class="group">
                                        <summary class="list-none rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Detail
                                        </summary>
                                        <div class="mt-2 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-sm">
                                            <p class="font-semibold text-slate-800">{{ $document['name'] }}</p>
                                            <p class="mt-2">Tahap: {{ $document['stage'] }}</p>
                                            <p class="mt-1">Tindakan: {{ $document['action'] }}</p>
                                            <p class="mt-1">Pengaju: {{ $document['owner'] }}</p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada dokumen yang perlu diproses oleh Anda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.scrollable-table>
            </x-ui.panel>
        @endif

        @if ($activeTab === 'my-history')
            <x-ui.panel
                title="Riwayat Pengajuan Saya"
                description="Dokumen yang diajukan oleh user login dan masih berjalan sebelum menjadi dokumen master."
                :padded="false"
            >
                <x-ui.scrollable-table max-height="560px" min-width="980px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diajukan</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Menunggu</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Update</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($filteredMySubmissionHistory as $document)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $document['number'] }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['type'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['submitted_at'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['waiting_for'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['updated_at'] }}</td>
                                <td class="px-5 py-4">
                                    <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
                                </td>
                                <td class="px-5 py-4">
                                    <details class="group">
                                        <summary class="list-none rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Detail
                                        </summary>
                                        <div class="mt-2 w-64 rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600 shadow-sm">
                                            <p class="font-semibold text-slate-800">{{ $document['name'] }}</p>
                                            <p class="mt-2">Diajukan: {{ $document['submitted_at'] }}</p>
                                            <p class="mt-1">Tahap: {{ $document['stage'] }}</p>
                                            <p class="mt-1">Menunggu: {{ $document['waiting_for'] }}</p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada riwayat pengajuan yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.scrollable-table>
            </x-ui.panel>
        @endif
    </div>
</x-layouts::app>
