<x-layouts::app :title="__('Catatan Aktivitas')">
    @php
        $filters = [
            'document_name' => trim((string) request('document_name', '')),
            'document_number' => trim((string) request('document_number', '')),
            'downloaded_by' => trim((string) request('downloaded_by', '')),
        ];

        $activities = [
            ['name' => 'Pengadaan Material Stock dan Material Konsinyasi', 'number' => 'IK-PGD-01-02', 'revision' => '00.02', 'downloaded_by' => 'Ismail Aulia Rahman', 'downloaded_at' => '10/08/2026 13:19', 'count' => 13],
            ['name' => 'Pembuatan LSTP', 'number' => 'IK-PGD-01-06', 'revision' => '00.06', 'downloaded_by' => 'Veryantoyo Eka Yunanda', 'downloaded_at' => '10/08/2026 12:25', 'count' => 35],
            ['name' => 'Berita Acara Pelayanan Jasa', 'number' => 'IK-PMS-01-02', 'revision' => '00.01', 'downloaded_by' => 'Adi Wibowo', 'downloaded_at' => '10/08/2026 11:36', 'count' => 25],
            ['name' => 'Penerbitan Invoice dan Faktur Pajak', 'number' => 'IK-KEU-01-03', 'revision' => '01.00', 'downloaded_by' => 'Rina Puspita', 'downloaded_at' => '10/08/2026 10:12', 'count' => 18],
            ['name' => 'Pengendalian Dokumen dan Rekaman', 'number' => 'IK-SMR-01-04', 'revision' => '00.01', 'downloaded_by' => 'Admin Kontrol Dokumen', 'downloaded_at' => '09/08/2026 16:45', 'count' => 42],
            ['name' => 'SOP Penanganan Temuan Audit', 'number' => 'SOP-QA-04-01', 'revision' => '01.00', 'downloaded_by' => 'Dewi Lestari', 'downloaded_at' => '09/08/2026 15:02', 'count' => 21],
            ['name' => 'Form Checklist Inspeksi Area Dermaga', 'number' => 'FM-HSE-01-08', 'revision' => '00.02', 'downloaded_by' => 'Bima Pratama', 'downloaded_at' => '09/08/2026 13:28', 'count' => 9],
        ];

        $filteredActivities = collect($activities)
            ->filter(fn ($activity) => $filters['document_name'] === '' || str_contains(strtolower($activity['name']), strtolower($filters['document_name'])))
            ->filter(fn ($activity) => $filters['document_number'] === '' || str_contains(strtolower($activity['number']), strtolower($filters['document_number'])))
            ->filter(fn ($activity) => $filters['downloaded_by'] === '' || str_contains(strtolower($activity['downloaded_by']), strtolower($filters['downloaded_by'])))
            ->values();
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Catatan Aktivitas" />

        <x-ui.panel
            title="List Aktivitas"
            description="Menampilkan {{ $filteredActivities->count() }} aktivitas dari total {{ count($activities) }} aktivitas."
            :padded="false"
        >
            <x-slot:actions>
                <x-ui.action-button type="button" class="gap-2">
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    Export Dokumen
                </x-ui.action-button>
            </x-slot:actions>

            <form method="GET" action="{{ route('activity-log.index') }}" class="grid gap-4 border-b border-slate-200 p-5 lg:grid-cols-3">
                <x-ui.input
                    label="Nama Dokumen"
                    name="document_name"
                    :value="$filters['document_name']"
                    placeholder="Cari nama dokumen"
                />

                <x-ui.input
                    label="Nomor Dokumen"
                    name="document_number"
                    :value="$filters['document_number']"
                    placeholder="Cari nomor dokumen"
                />

                <x-ui.input
                    label="Diunduh Oleh"
                    name="downloaded_by"
                    :value="$filters['downloaded_by']"
                    placeholder="Cari diunduh oleh"
                />
            </form>

            <x-ui.scrollable-table max-height="620px" min-width="1000px" class="text-base">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diunduh Oleh</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Waktu Unduh</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diunduh Ke</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($filteredActivities as $activity)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-5 font-semibold uppercase leading-7 text-slate-800">{{ $activity['name'] }}</td>
                            <td class="px-5 py-5 font-semibold text-slate-700">{{ $activity['number'] }}</td>
                            <td class="px-5 py-5 text-slate-600">{{ $activity['revision'] }}</td>
                            <td class="px-5 py-5 font-medium uppercase leading-7 text-slate-700">{{ $activity['downloaded_by'] }}</td>
                            <td class="px-5 py-5 text-slate-600">{{ $activity['downloaded_at'] }}</td>
                            <td class="px-5 py-5 font-semibold text-slate-700">{{ $activity['count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada aktivitas yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts::app>
