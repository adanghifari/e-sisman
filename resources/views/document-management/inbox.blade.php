<x-layouts::app :title="__('Inbox Approval')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Inbox Approval"
            description="Pantau dokumen yang perlu Anda proses dan riwayat approval yang sudah Anda lakukan."
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
                title="Perlu Saya Proses"
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
                                    <a href="{{ $document['detail_url'] }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                                        Detail
                                    </a>
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

        @if ($activeTab === 'processed-history')
            <x-ui.panel
                title="Riwayat yang Saya Proses"
                description="Dokumen yang approval-nya sudah pernah Anda proses."
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
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diproses</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($filteredMyProcessedHistory as $document)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $document['number'] }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $document['name'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['type'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['submitted_at'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['stage'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['owner'] }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $document['updated_at'] }}</td>
                                <td class="px-5 py-4">
                                    <x-ui.status-badge :label="$document['status']" :tone="$document['tone']" />
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ $document['detail_url'] }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                                        Detail
                                    </a>
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
