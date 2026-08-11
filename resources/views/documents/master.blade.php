<x-layouts::app :title="__('Dokumen Master')">
    @php
        $filters = [
            'search' => trim((string) request('search', '')),
            'type' => (string) request('type', ''),
            'process' => (string) request('process', ''),
            'stamp' => (string) request('stamp', ''),
            'sort' => (string) request('sort', 'newest'),
        ];

        $documents = [
            ['name' => 'Tinjauan Manajemen', 'number' => 'IK-MRI-01-03', 'revision' => '00.00', 'level' => 'Instruksi Kerja', 'process' => 'Manajemen Strategis', 'published_at' => '01/03/2024', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'MR & QA', 'location' => 'Dokumen Master'],
            ['name' => 'Audit Internal Sistem Manajemen', 'number' => 'IK-MRI-01-04', 'revision' => '00.00', 'level' => 'Instruksi Kerja', 'process' => 'Manajemen Strategis', 'published_at' => '01/03/2024', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'Internal Audit', 'location' => 'Dokumen Master'],
            ['name' => 'Pengendalian Dokumen dan Rekaman', 'number' => 'IK-SMR-01-04', 'revision' => '00.01', 'level' => 'Instruksi Kerja', 'process' => 'Manajemen Strategis', 'published_at' => '06/10/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'Admin Kontrol Dokumen', 'location' => 'Dokumen Master', 'obsolete_documents' => [
                ['number' => 'IK-SMR-01-04', 'revision' => '00.00', 'published_at' => '01/03/2024', 'reason' => 'Digantikan oleh revisi 00.01'],
            ]],
            ['name' => 'Pemilihan Boat dan Marine Service', 'number' => 'IK-HOM-01-02', 'revision' => '01.00', 'level' => 'Instruksi Kerja', 'process' => 'Proses Bisnis', 'published_at' => '12/07/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'Operasional', 'location' => 'Dokumen Master'],
            ['name' => 'Prosedur Pengelolaan Risiko Operasional', 'number' => 'PR-RSK-02-01', 'revision' => '02.00', 'level' => 'Prosedur', 'process' => 'Manajemen Risiko', 'published_at' => '19/05/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'Risk Management', 'location' => 'Dokumen Master'],
            ['name' => 'Form Checklist Inspeksi Area Dermaga', 'number' => 'FM-HSE-01-08', 'revision' => '00.02', 'level' => 'Form', 'process' => 'HSE', 'published_at' => '22/04/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'HSE', 'location' => 'Dokumen Master'],
            ['name' => 'Prosedur Evaluasi Supplier', 'number' => 'PR-PBJ-03-02', 'revision' => '01.01', 'level' => 'Prosedur', 'process' => 'Pengadaan', 'published_at' => '14/03/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'Procurement', 'location' => 'Dokumen Master'],
            ['name' => 'IK Backup Database Aplikasi Internal', 'number' => 'IK-IT-02-09', 'revision' => '00.00', 'level' => 'Instruksi Kerja', 'process' => 'Teknologi Informasi', 'published_at' => '28/02/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'IT', 'location' => 'Dokumen Master'],
            ['name' => 'SOP Penanganan Temuan Audit', 'number' => 'SOP-QA-04-01', 'revision' => '01.00', 'level' => 'SOP', 'process' => 'Quality Assurance', 'published_at' => '16/01/2025', 'stamp' => 'Master', 'tone' => 'sky', 'owner' => 'QA', 'location' => 'Dokumen Master', 'obsolete_documents' => [
                ['number' => 'SOP-QA-04-01', 'revision' => '00.00', 'published_at' => '20/08/2024', 'reason' => 'Digantikan oleh revisi 01.00'],
            ]],
            ['name' => 'Instruksi Kerja Pengarsipan Digital', 'number' => 'IK-PB-05-11', 'revision' => '00.03', 'level' => 'Instruksi Kerja', 'process' => 'Pengendalian Dokumen', 'published_at' => '04/12/2024', 'stamp' => 'Obsolete', 'tone' => 'red', 'owner' => 'Admin Kontrol Dokumen', 'location' => 'Arsip Obsolete'],
        ];

        $typeOptions = ['' => 'Semua Level'] + collect($documents)->pluck('level')->unique()->sort()->mapWithKeys(fn ($level) => [$level => $level])->all();
        $processOptions = ['' => 'Semua Proses'] + collect($documents)->pluck('process')->unique()->sort()->mapWithKeys(fn ($process) => [$process => $process])->all();
        $stampOptions = ['' => 'Semua Stamp'] + collect($documents)->pluck('stamp')->unique()->sort()->mapWithKeys(fn ($stamp) => [$stamp => $stamp])->all();
        $sortOptions = [
            'newest' => 'Terbaru',
            'oldest' => 'Terlama',
            'name_asc' => 'Nama A-Z',
            'name_desc' => 'Nama Z-A',
            'revision_desc' => 'Revisi Tertinggi',
        ];

        $filteredDocuments = collect($documents)
            ->filter(function ($document) use ($filters) {
                $haystack = strtolower(implode(' ', [
                    $document['name'],
                    $document['number'],
                    $document['level'],
                    $document['process'],
                    $document['stamp'],
                    $document['owner'],
                ]));

                return ($filters['search'] === '' || str_contains($haystack, strtolower($filters['search'])))
                    && ($filters['type'] === '' || $document['level'] === $filters['type'])
                    && ($filters['process'] === '' || $document['process'] === $filters['process'])
                    && ($filters['stamp'] === '' || $document['stamp'] === $filters['stamp']);
            })
            ->sortBy(function ($document) use ($filters) {
                return match ($filters['sort']) {
                    'oldest', 'newest' => $document['published_at'],
                    'name_desc', 'name_asc' => $document['name'],
                    'revision_desc' => $document['revision'],
                    default => $document['published_at'],
                };
            }, SORT_NATURAL, in_array($filters['sort'], ['newest', 'name_desc', 'revision_desc'], true))
            ->values();
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            title="Dokumen Master"
        />

        <x-ui.filter-bar :action="route('documents.master')">
            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nama atau nomor dokumen..."
            />

            <x-ui.select label="Dok Level" name="type" :value="$filters['type']" :options="$typeOptions" />
            <x-ui.select label="Proses / Fungsi" name="process" :value="$filters['process']" :options="$processOptions" />
            <x-ui.select label="Stamp" name="stamp" :value="$filters['stamp']" :options="$stampOptions" />
            <x-ui.select label="Urutkan" name="sort" :value="$filters['sort']" :options="$sortOptions" />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('documents.master') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <x-ui.panel
            title="Daftar Induk Dokumen Master"
            description="Menampilkan {{ $filteredDocuments->count() }} dokumen dari total {{ count($documents) }} dokumen."
            :padded="false"
        >
            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[2.5%]">
                    <col class="w-[29%]">
                    <col class="w-[12.5%]">
                    <col class="w-[6.5%]">
                    <col class="w-[11.5%]">
                    <col class="w-[15%]">
                    <col class="w-[9%]">
                    <col class="w-[6%]">
                    <col class="w-[8%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold"></th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Dok Level</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Proses / Fungsi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Tgl Terbit</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Stamp</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                @forelse ($filteredDocuments as $document)
                    <tbody class="is-row-group border-b border-slate-100">
                        @php
                            $obsoleteDocuments = $document['obsolete_documents'] ?? [];
                            $hasObsoleteDocuments = count($obsoleteDocuments) > 0;
                        @endphp

                        <tr class="hover:bg-slate-50/70">
                            <td class="px-1 py-4">
                                @if ($hasObsoleteDocuments)
                                    <x-ui.icon-button
                                        icon="chevron-right"
                                        label="Tampilkan dokumen obsolete"
                                        variant="ghost"
                                        size="sm"
                                        data-table-row-toggle="{{ $document['number'] }}"
                                        aria-expanded="false"
                                    />
                                @endif
                            </td>
                            <td class="px-3 py-4 font-semibold uppercase tracking-wide text-slate-800">{{ $document['name'] }}</td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $document['number'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document['revision'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document['level'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document['process'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document['published_at'] }}</td>
                            <td class="px-3 py-4">
                                <x-ui.status-badge :label="$document['stamp']" :tone="$document['tone']" />
                            </td>
                            <td class="px-2 py-4">
                                <x-ui.icon-button icon="eye" label="Lihat detail" size="sm" />
                            </td>
                        </tr>

                        @foreach ($obsoleteDocuments as $obsolete)
                            <tr class="is-child-row hidden" data-table-row-target="{{ $document['number'] }}">
                                <td class="px-1 py-3"></td>
                                <td class="px-3 py-3 pl-8">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dokumen Obsolete</p>
                                    <p class="mt-1 font-semibold uppercase tracking-wide text-slate-700">{{ $document['name'] }}</p>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-600">{{ $obsolete['number'] }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $obsolete['revision'] }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $document['level'] }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $document['process'] }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $obsolete['published_at'] }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge label="Obsolete" tone="red" />
                                </td>
                                <td class="px-2 py-3">
                                    <x-ui.icon-button icon="eye" label="Lihat detail" size="sm" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @empty
                    <tbody>
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada dokumen master yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts::app>
