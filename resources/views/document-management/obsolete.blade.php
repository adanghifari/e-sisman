<x-layouts::app :title="__('Dokumen Obsolete')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Dokumen Obsolete"
            description="Daftar dokumen yang sudah berstatus obsolete."
        />

        <x-ui.filter-bar :action="route('documents.obsolete')">
            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nama, nomor, level, proses, atau department..."
            />

            <x-ui.select label="Dok Level" name="type" :value="$filters['type']" :options="$typeOptions" />
            <x-ui.select label="Proses Bisnis" name="process" :value="$filters['process']" :options="$processOptions" />
            <x-ui.select label="Urutkan" name="sort" :value="$filters['sort']" :options="$sortOptions" />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('documents.obsolete') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <div class="flex justify-end">
            <a
                href="{{ route('documents.master') }}"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                wire:navigate
            >
                <flux:icon name="plus" class="size-4" />
                Tambah Dokumen Obsolete
            </a>
        </div>

        <x-ui.panel
            title="Daftar Dokumen Obsolete"
            description="Menampilkan {{ $documents->count() }} dokumen dari total {{ $totalDocuments }} dokumen obsolete."
            :padded="false"
        >
            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[30%]">
                    <col class="w-[14%]">
                    <col class="w-[8%]">
                    <col class="w-[18%]">
                    <col class="w-[11%]">
                    <col class="w-[11%]">
                    <col class="w-[8%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Proses / Fungsi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Tgl Terbit</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Stamp</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($documents as $document)
                        @php
                            $publishedAt = $document->tanggal_terbit ?? $document->approved_at;
                            $processLabel = collect([
                                $document->businessProcess?->nama_proses_bisnis,
                                $document->businessFunction?->nama_proses_fungsi,
                            ])->filter()->implode(' / ');
                        @endphp

                        <tr class="hover:bg-slate-50/70">
                            <td class="px-3 py-4">
                                <p class="font-semibold uppercase tracking-wide text-slate-800">{{ $document->nama_dokumen }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">
                                    {{ $document->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department' }}
                                </p>
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $document->nomor_dokumen ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->formatted_revision }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $processLabel ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $publishedAt?->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-3 py-4">
                                <x-ui.status-badge label="Obsolete" tone="red" />
                            </td>
                            <td class="px-2 py-4">
                                <x-ui.icon-button :href="route('documents.master.show', $document)" icon="eye" label="Lihat detail" size="sm" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada dokumen obsolete yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts::app>
