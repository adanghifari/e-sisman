<x-layouts::app :title="__('Dashboard')">
    @php
        $needsProcess = [
            ['number' => 'KBS-PB-PR-001', 'name' => 'Prosedur Pengendalian Dokumen', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'owner' => 'Rendy Aulia', 'date' => '10 Agu 2026', 'status' => 'Menunggu'],
            ['number' => 'KBS-OPS-IK-014', 'name' => 'Instruksi Kerja Bongkar Muat Curah', 'type' => 'IK', 'stage' => 'Review Kadis', 'owner' => 'Oktavia Putri', 'date' => '09 Agu 2026', 'status' => 'Review'],
            ['number' => 'KBS-HSE-IK-008', 'name' => 'Instruksi Kerja Inspeksi Area Dermaga', 'type' => 'IK', 'stage' => 'Approval Manager', 'owner' => 'Aditya Chandra', 'date' => '08 Agu 2026', 'status' => 'Approval'],
            ['number' => 'KBS-QA-FM-011', 'name' => 'Form Checklist Audit Mutu Internal', 'type' => 'Form', 'stage' => 'Verifikasi Admin', 'owner' => 'Siska Amelia', 'date' => '08 Agu 2026', 'status' => 'Menunggu'],
            ['number' => 'KBS-HC-PR-004', 'name' => 'Prosedur Onboarding Karyawan Baru', 'type' => 'Prosedur', 'stage' => 'Review Kadis', 'owner' => 'Bima Pratama', 'date' => '07 Agu 2026', 'status' => 'Review'],
            ['number' => 'KBS-FIN-IK-006', 'name' => 'IK Rekonsiliasi Pembayaran Vendor', 'type' => 'IK', 'stage' => 'Approval Manager', 'owner' => 'Dian Kartika', 'date' => '07 Agu 2026', 'status' => 'Approval'],
            ['number' => 'KBS-OPS-PR-018', 'name' => 'Prosedur Penjadwalan Kapal Sandar', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'owner' => 'Naufal Rizky', 'date' => '06 Agu 2026', 'status' => 'Menunggu'],
            ['number' => 'KBS-HSE-FM-003', 'name' => 'Form Laporan Insiden Keselamatan', 'type' => 'Form', 'stage' => 'Review Kadis', 'owner' => 'Maya Safitri', 'date' => '06 Agu 2026', 'status' => 'Review'],
            ['number' => 'KBS-IT-IK-009', 'name' => 'IK Backup Database Aplikasi Internal', 'type' => 'IK', 'stage' => 'Approval Manager', 'owner' => 'Fajar Nugroho', 'date' => '05 Agu 2026', 'status' => 'Approval'],
            ['number' => 'KBS-PB-PR-013', 'name' => 'Prosedur Distribusi Dokumen Terkendali', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'owner' => 'Ayu Lestari', 'date' => '05 Agu 2026', 'status' => 'Menunggu'],
        ];

        $approvalMonitoring = [
            ['name' => 'Revisi Prosedur Audit Internal', 'stage' => 'Manager', 'waiting_for' => 'Manager', 'status' => 'Dalam Approval', 'tone' => 'sky', 'icon' => 'clock', 'current_step' => 2, 'total_steps' => 5],
            ['name' => 'IK Pengelolaan Template Dokumen', 'stage' => 'Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'icon' => 'pencil-square', 'current_step' => 1, 'total_steps' => 4],
            ['name' => 'Prosedur Penerbitan Dokumen Master', 'stage' => 'Publish', 'waiting_for' => 'Admin Dokumen Master', 'status' => 'Approved', 'tone' => 'emerald', 'icon' => 'check-circle', 'current_step' => 4, 'total_steps' => 4],
            ['name' => 'Revisi IK Pemeriksaan Alat Angkat', 'stage' => 'Kadis HSE', 'waiting_for' => 'Kadis HSE', 'status' => 'Dalam Approval', 'tone' => 'sky', 'icon' => 'clock', 'current_step' => 3, 'total_steps' => 5],
            ['name' => 'Form Checklist Inspeksi Harian', 'stage' => 'Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'icon' => 'pencil-square', 'current_step' => 1, 'total_steps' => 3],
            ['name' => 'Prosedur Pengelolaan Risiko Operasional', 'stage' => 'Manager Risiko', 'waiting_for' => 'Manager Risiko', 'status' => 'Dalam Approval', 'tone' => 'sky', 'icon' => 'clock', 'current_step' => 2, 'total_steps' => 6],
            ['name' => 'IK Kalibrasi Peralatan Ukur', 'stage' => 'Publish', 'waiting_for' => 'Admin Dokumen Master', 'status' => 'Approved', 'tone' => 'emerald', 'icon' => 'check-circle', 'current_step' => 5, 'total_steps' => 5],
            ['name' => 'SOP Penanganan Temuan Audit', 'stage' => 'Admin', 'waiting_for' => 'Admin Kontrol Dokumen', 'status' => 'Perlu Koreksi', 'tone' => 'amber', 'icon' => 'pencil-square', 'current_step' => 2, 'total_steps' => 5],
            ['name' => 'Instruksi Kerja Pengarsipan Digital', 'stage' => 'Manager', 'waiting_for' => 'Manager Operasional', 'status' => 'Dalam Approval', 'tone' => 'sky', 'icon' => 'clock', 'current_step' => 3, 'total_steps' => 4],
            ['name' => 'Prosedur Evaluasi Supplier', 'stage' => 'Publish', 'waiting_for' => 'Admin Dokumen Master', 'status' => 'Approved', 'tone' => 'emerald', 'icon' => 'check-circle', 'current_step' => 4, 'total_steps' => 4],
        ];

        $submissionToneClasses = [
            'amber' => [
                'item' => 'border-amber-200 bg-amber-50/55',
                'icon' => 'bg-amber-100 text-amber-700',
                'bar' => 'bg-amber-500',
                'meta' => 'text-amber-800',
            ],
            'emerald' => [
                'item' => 'border-emerald-200 bg-emerald-50/55',
                'icon' => 'bg-emerald-100 text-emerald-700',
                'bar' => 'bg-emerald-500',
                'meta' => 'text-emerald-800',
            ],
            'sky' => [
                'item' => 'border-sky-200 bg-sky-50/55',
                'icon' => 'bg-sky-100 text-sky-700',
                'bar' => 'bg-sky-500',
                'meta' => 'text-sky-800',
            ],
        ];

        $documentStatusSummary = [
            ['label' => 'Dalam Penyusunan', 'value' => 18, 'tone' => 'slate'],
            ['label' => 'Verifikasi Admin', 'value' => 12, 'tone' => 'sky'],
            ['label' => 'Dalam Approval', 'value' => 24, 'tone' => 'sky'],
            ['label' => 'Perlu Koreksi', 'value' => 7, 'tone' => 'amber'],
            ['label' => 'Dokumen Master', 'value' => 186, 'tone' => 'emerald'],
            ['label' => 'Obsolete', 'value' => 9, 'tone' => 'red'],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Dashboard">
            <x-ui.action-button :href="route('documents.create')">
                Tambah Dokumen
            </x-ui.action-button>
        </x-ui.page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
            <div class="space-y-6">
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    @foreach ($summaryCards as $card)
                        <a href="{{ route('documents.inbox', ['tab' => $card['tab']]) }}" class="block transition hover:-translate-y-0.5 hover:shadow-sm" wire:navigate>
                            <x-ui.summary-card
                                :label="$card['label']"
                                :value="$card['value']"
                                :hint="$card['hint']"
                            />
                        </a>
                    @endforeach
                </div>

                <x-ui.panel
                    title="Dokumen Butuh Diproses"
                    description="Dokumen yang perlu ditindaklanjuti oleh Admin."
                    :padded="false"
                >
                    <x-slot:actions>
                        <a href="{{ route('documents.inbox') }}" class="shrink-0 text-sm font-semibold text-sky-700" wire:navigate>Lihat semua</a>
                    </x-slot:actions>

                    <x-ui.scrollable-table>
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tanggal</th>
                                <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($needsProcess as $document)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4 font-semibold text-slate-800">{{ $document['number'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $document['type'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $document['owner'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $document['date'] }}</td>
                                    <td class="px-5 py-4">
                                        <x-ui.status-badge :label="$document['status']" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.scrollable-table>
                </x-ui.panel>

                <x-ui.panel
                    title="Ringkasan Status Dokumen"
                    description="Snapshot jumlah dokumen berdasarkan status utama."
                >

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($documentStatusSummary as $status)
                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-medium leading-snug text-slate-600">{{ $status['label'] }}</p>
                                    <x-ui.status-badge :label="$status['value']" :tone="$status['tone']" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.panel>
            </div>

            <div class="space-y-6">
                <x-ui.panel
                    title="Aktivitas Terbaru"
                    description="Perubahan dokumen terbaru."
                >
                    <x-slot:actions>
                        <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ $activities->count() }} item
                        </span>
                    </x-slot:actions>

                    <x-ui.scroll-area max-height="190px" class="mt-4 space-y-4 pr-2">
                        @forelse ($activities as $activity)
                            <div class="flex gap-3">
                                <span class="mt-1 size-2 rounded-full bg-sky-500"></span>
                                <div class="min-w-0">
                                    <p class="text-sm leading-5 text-slate-700">{{ $activity['text'] }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-400">{{ $activity['time'] }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm font-medium text-slate-500">
                                Belum ada aktivitas terbaru.
                            </div>
                        @endforelse
                    </x-ui.scroll-area>
                </x-ui.panel>

                <x-ui.panel
                    title="Monitoring Approval"
                    description="Pantau posisi dokumen yang sedang berjalan."
                >
                    <x-slot:actions>
                        <a href="{{ route('documents.inbox') }}" class="shrink-0 rounded-md bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100" wire:navigate>
                            Lihat semua
                        </a>
                    </x-slot:actions>

                    <x-ui.scroll-area max-height="520px" class="mt-4 space-y-3 pr-2">
                        @foreach ($approvalMonitoring as $document)
                            @php
                                $tone = $submissionToneClasses[$document['tone']] ?? $submissionToneClasses['sky'];
                                $progressPercent = min(100, max(0, (int) round(($document['current_step'] / max($document['total_steps'], 1)) * 100)));
                            @endphp

                            <div class="rounded-lg border p-3.5 {{ $tone['item'] }}">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-md {{ $tone['icon'] }}">
                                        <flux:icon :name="$document['icon']" class="size-4" />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="font-medium leading-snug text-slate-900">{{ $document['name'] }}</p>
                                            <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
                                        </div>

                                        <p class="mt-1.5 text-sm text-slate-600">
                                            Menunggu persetujuan dari {{ $document['waiting_for'] }}.
                                        </p>

                                        <div class="mt-3">
                                            <div class="flex items-center justify-between gap-3 text-xs font-semibold">
                                                <span class="{{ $tone['meta'] }}">Tahap {{ $document['stage'] }}</span>
                                                <span class="text-slate-500">{{ $document['current_step'] }} dari {{ $document['total_steps'] }}</span>
                                            </div>
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/80">
                                                <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $progressPercent }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </x-ui.scroll-area>
                </x-ui.panel>
            </div>
        </div>
    </div>
</x-layouts::app>
