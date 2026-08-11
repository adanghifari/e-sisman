<x-layouts::app :title="__('Dashboard')">
    @php
        $summaryCards = [
            ['label' => 'Butuh Diproses', 'value' => '12', 'hint' => '4 verifikasi admin', 'tone' => 'blue'],
            ['label' => 'Pengajuan Aktif', 'value' => '28', 'hint' => '7 menunggu approval', 'tone' => 'cyan'],
            ['label' => 'Dikembalikan', 'value' => '5', 'hint' => 'perlu perbaikan user', 'tone' => 'amber'],
            ['label' => 'Dokumen Master', 'value' => '186', 'hint' => '12 publish bulan ini', 'tone' => 'emerald'],
        ];

        $needsProcess = [
            ['number' => 'KBS-PB-PR-001', 'name' => 'Prosedur Pengendalian Dokumen', 'type' => 'Prosedur', 'stage' => 'Verifikasi Admin', 'owner' => 'Rendy Aulia', 'date' => '10 Agu 2026', 'status' => 'Menunggu'],
            ['number' => 'KBS-OPS-IK-014', 'name' => 'Instruksi Kerja Bongkar Muat Curah', 'type' => 'IK', 'stage' => 'Review Kadis', 'owner' => 'Oktavia Putri', 'date' => '09 Agu 2026', 'status' => 'Review'],
            ['number' => 'KBS-HSE-IK-008', 'name' => 'Instruksi Kerja Inspeksi Area Dermaga', 'type' => 'IK', 'stage' => 'Approval Manager', 'owner' => 'Aditya Chandra', 'date' => '08 Agu 2026', 'status' => 'Approval'],
        ];

        $mySubmissions = [
            ['name' => 'Revisi Prosedur Audit Internal', 'progress' => 'Manager', 'status' => 'Dalam Approval'],
            ['name' => 'IK Pengelolaan Template Dokumen', 'progress' => 'Admin', 'status' => 'Perlu Koreksi'],
            ['name' => 'Prosedur Penerbitan Dokumen Master', 'progress' => 'Publish', 'status' => 'Approved'],
        ];

        $activities = [
            ['text' => 'Admin mengembalikan IK Pengelolaan Template Dokumen', 'time' => '10:42'],
            ['text' => 'Manager menyetujui KBS-HSE-IK-008', 'time' => '09:15'],
            ['text' => 'Dokumen KBS-PB-PR-001 masuk verifikasi admin', 'time' => '08:30'],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            title="Dashboard"
            :description="'Selamat datang, '.auth()->user()->name.'. Pantau proses dokumen dan approval dari satu halaman.'"
        >
            <x-ui.action-button :href="route('documents.create')">
                Tambah Dokumen
            </x-ui.action-button>
        </x-ui.page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <x-ui.summary-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :hint="$card['hint']"
                    badge="Live"
                />
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="font-semibold text-slate-950">Dokumen Butuh Diproses</h2>
                        <p class="text-sm text-slate-500">Dummy data untuk preview workflow approval.</p>
                    </div>
                    <a href="{{ route('documents.inbox') }}" class="text-sm font-semibold text-sky-700" wire:navigate>Lihat semua</a>
                </div>

                <x-ui.data-table>
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Nomor</th>
                                <th class="px-5 py-3 font-semibold">Dokumen</th>
                                <th class="px-5 py-3 font-semibold">Jenis</th>
                                <th class="px-5 py-3 font-semibold">Tahap</th>
                                <th class="px-5 py-3 font-semibold">Pengaju</th>
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
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
                </x-ui.data-table>
            </section>

            <aside class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-slate-950">Status Pengajuan Saya</h2>
                    <div class="mt-4 space-y-4">
                        @foreach ($mySubmissions as $submission)
                            <div class="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                                <p class="font-medium text-slate-800">{{ $submission['name'] }}</p>
                                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                                    <span class="text-slate-500">Tahap: {{ $submission['progress'] }}</span>
                                    <span class="font-semibold text-sky-700">{{ $submission['status'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-slate-950">Aktivitas Terbaru</h2>
                    <div class="mt-4 space-y-4">
                        @foreach ($activities as $activity)
                            <div class="flex gap-3">
                                <span class="mt-1 size-2 rounded-full bg-sky-500"></span>
                                <div>
                                    <p class="text-sm text-slate-700">{{ $activity['text'] }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-400">{{ $activity['time'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
