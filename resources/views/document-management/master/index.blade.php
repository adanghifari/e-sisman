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
            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[4%]">
                    <col class="w-[30%]">
                    <col class="w-[14%]">
                    <col class="w-[8%]">
                    <col class="w-[18%]">
                    <col class="w-[11%]">
                    <col class="w-[8%]">
                    <col class="w-[7%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold"></th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
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
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $document->master_display_number ?: $document->nomor_dokumen ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->formatted_revision }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $processLabel ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $publishedAt?->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-3 py-4">
                                <x-ui.status-badge label="Master" tone="sky" />
                            </td>
                            <td class="px-2 py-4">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon-button :href="route('documents.master.show', $document)" icon="eye" label="Lihat detail" size="sm" />
                                </div>
                            </td>
                        </tr>

                        @if ($hasObsoleteDocuments)
                            <tr class="is-child-row hidden bg-slate-50/40" data-table-row-target="{{ $rowKey }}">
                                <td colspan="8" class="px-0 py-0">
                                    <div class="relative py-3 pl-14 pr-5">
                                        <span class="absolute left-6 top-0 h-1/2 border-l border-slate-300"></span>
                                        <span class="absolute left-6 top-1/2 w-8 border-t border-slate-300"></span>

                                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                                            <table class="w-full table-fixed text-sm">
                                                <colgroup>
                                                    <col class="w-[26%]">
                                                    <col class="w-[11%]">
                                                    <col class="w-[17%]">
                                                    <col class="w-[18%]">
                                                    <col class="w-[16%]">
                                                    <col class="w-[12%]">
                                                </colgroup>
                                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                                    <tr>
                                                        <th class="px-5 py-3 text-left font-semibold">Nama Dokumen</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Revisi</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Tgl Terbit</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Tgl Obsolete</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Stamp</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($obsoleteDocuments as $obsolete)
                                                        @php
                                                            $obsoletePublishedAt = $obsolete->tanggal_terbit ?? $obsolete->approved_at;
                                                            $obsoleteDate = $obsolete->master_obsolete_date;
                                                        @endphp

                                                        <tr>
                                                            <td class="px-5 py-4 font-semibold uppercase tracking-wide text-slate-700">
                                                                {{ $obsolete->master_display_number ?: $obsolete->nomor_dokumen ?: $obsolete->nama_dokumen }}
                                                            </td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $obsolete->formatted_revision }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $obsoletePublishedAt?->format('d/m/Y') ?: '-' }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $obsoleteDate?->format('d/m/Y') ?: '-' }}</td>
                                                            <td class="px-5 py-4">
                                                                <x-ui.status-badge label="Obsolete" tone="red" />
                                                            </td>
                                                            <td class="px-5 py-4">
                                                                <x-ui.icon-button :href="route('documents.obsolete.show', $obsolete)" icon="eye" label="Lihat detail obsolete" size="sm" />
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada dokumen master yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts::app>
