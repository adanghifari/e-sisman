<x-layouts::app :title="__('Dokumen Butuh Diproses')">
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
