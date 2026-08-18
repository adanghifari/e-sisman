<x-layouts::app :title="__('Dokumen Master')">
    <div class="space-y-6">
        <x-ui.page-header title="Dokumen Master" />

        <x-ui.filter-bar :action="route('documents.master')">
            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nama, nomor, level, proses, atau department..."
            />

            <x-ui.select label="Dok Level" name="type" :value="$filters['type']" :options="$typeOptions" />
            <x-ui.select label="Proses Bisnis" name="process" :value="$filters['process']" :options="$processOptions" />
            <x-ui.select label="Stamp" name="stamp" :value="$filters['stamp']" :options="$stampOptions" disabled />
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
            description="Menampilkan {{ $documents->count() }} dokumen dari total {{ $totalDocuments }} dokumen master."
            :padded="false"
        >
            <x-ui.scrollable-table max-height="620px" min-width="1120px" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[3%]">
                    <col class="w-[27%]">
                    <col class="w-[13%]">
                    <col class="w-[7%]">
                    <col class="w-[12%]">
                    <col class="w-[17%]">
                    <col class="w-[10%]">
                    <col class="w-[7%]">
                    <col class="w-[4%]">
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

                @forelse ($documents as $document)
                    @php
                        $obsoleteDocuments = $document->getRelation('masterObsoleteDocuments');
                        $hasObsoleteDocuments = $obsoleteDocuments->isNotEmpty();
                        $rowKey = 'master-document-'.$document->id;
                        $publishedAt = $document->tanggal_terbit ?? $document->approved_at;
                        $processLabel = collect([
                            $document->businessProcess?->nama_proses_bisnis,
                            $document->businessFunction?->nama_proses_fungsi,
                        ])->filter()->implode(' / ');
                    @endphp

                    <tbody class="is-row-group border-b border-slate-100">
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-1 py-4">
                                @if ($hasObsoleteDocuments)
                                    <x-ui.icon-button
                                        icon="chevron-right"
                                        label="Tampilkan dokumen obsolete"
                                        variant="ghost"
                                        size="sm"
                                        data-table-row-toggle="{{ $rowKey }}"
                                        aria-expanded="false"
                                    />
                                @endif
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-semibold uppercase tracking-wide text-slate-800">{{ $document->nama_dokumen }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">
                                    {{ $document->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department' }}
                                </p>
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $document->nomor_dokumen ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->formatted_revision }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->documentLevel?->nama_dokumen ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $processLabel ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $publishedAt?->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-3 py-4">
                                <x-ui.status-badge label="Master" tone="sky" />
                            </td>
                            <td class="px-2 py-4">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon-button :href="route('documents.master.show', $document)" icon="eye" label="Lihat detail" size="sm" />
                                    @if ($document->can_request_revision)
                                        <x-ui.icon-button
                                            :href="route('documents.create.level', [$document->documentLevel?->kode ?? 'level-3', 'revised_from' => $document->id])"
                                            icon="arrow-path"
                                            label="Ajukan revisi"
                                            size="sm"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @foreach ($obsoleteDocuments as $obsolete)
                            @php
                                $obsoletePublishedAt = $obsolete->tanggal_terbit ?? $obsolete->approved_at;
                                $obsoleteProcessLabel = collect([
                                    $obsolete->businessProcess?->nama_proses_bisnis,
                                    $obsolete->businessFunction?->nama_proses_fungsi,
                                ])->filter()->implode(' / ');
                            @endphp

                            <tr class="is-child-row hidden" data-table-row-target="{{ $rowKey }}">
                                <td class="px-1 py-3"></td>
                                <td class="px-3 py-3 pl-8">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dokumen Obsolete</p>
                                    <p class="mt-1 font-semibold uppercase tracking-wide text-slate-700">{{ $obsolete->nama_dokumen }}</p>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-600">{{ $obsolete->nomor_dokumen ?: '-' }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $obsolete->formatted_revision }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $obsolete->documentLevel?->nama_dokumen ?: '-' }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $obsoleteProcessLabel ?: '-' }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $obsoletePublishedAt?->format('d/m/Y') ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge label="Obsolete" tone="red" />
                                </td>
                                <td class="px-2 py-3">
                                    <x-ui.icon-button :href="route('documents.master.show', $obsolete)" icon="eye" label="Lihat detail obsolete" size="sm" />
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
