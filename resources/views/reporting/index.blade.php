<x-layouts::app :title="__('Overview')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Overview"
            description="Daftar dokumen master untuk kebutuhan laporan."
        />

        <x-ui.filter-bar :action="route('reports.index')">
            <x-ui.input
                label="Cari Prosedur / Nomor"
                name="procedure"
                :value="$overviewFilters['procedure']"
                placeholder="Cari prosedur atau nomor dokumen..."
            />

            <x-ui.input
                label="Cari Instruksi Kerja"
                name="instruction"
                :value="$overviewFilters['instruction']"
                placeholder="Cari instruksi kerja..."
            />

            <x-ui.select
                label="Department"
                name="department_id"
                :value="$overviewFilters['department_id']"
                :options="$departmentOptions"
            />

            <x-ui.select
                label="Proses / Fungsi"
                name="business_function_id"
                :value="$overviewFilters['business_function_id']"
                :options="$businessFunctionOptions"
            />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('reports.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <x-ui.panel
            title="Daftar Dokumen"
            description="Menampilkan {{ $overviewRows->count() }} dari {{ $overviewRows->total() }} prosedur."
            :padded="false"
        >
            <x-slot:actions>
                <a href="{{ route('reports.export', array_filter($overviewFilters, fn ($value) => $value !== '')) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    Export Dokumen
                </a>
            </x-slot:actions>

            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-sm">
                <colgroup>
                    <col class="w-[4%]">
                    <col class="w-[28%]">
                    <col class="w-[14%]">
                    <col class="w-[8%]">
                    <col class="w-[20%]">
                    <col class="w-[17%]">
                    <col class="w-[9%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold"></th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Prosedur</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Department</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Proses/Fungsi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Tgl Terbit</th>
                    </tr>
                </thead>

                @forelse ($overviewRows as $row)
                    @php
                        $rowKey = 'overview-procedure-'.$row['id'];
                        $instructions = $row['instructions'];
                    @endphp

                    <tbody class="is-row-group border-b border-slate-100">
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-1 py-4">
                                @if ($instructions->isNotEmpty())
                                    <x-ui.icon-button
                                        icon="chevron-right"
                                        label="Tampilkan instruksi kerja"
                                        variant="ghost"
                                        size="sm"
                                        data-table-row-toggle="{{ $rowKey }}"
                                        aria-expanded="false"
                                    />
                                @endif
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-semibold text-slate-800">{{ $row['procedure'] }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ $instructions->count() }} Instruksi Kerja</p>
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $row['number'] ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['revision'] }}</td>
                            <td class="px-3 py-4 text-slate-600">
                                {{ $row['departments']->isNotEmpty() ? $row['departments']->join(', ') : '-' }}
                            </td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['business_function'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['published_at'] }}</td>
                        </tr>

                        @if ($instructions->isNotEmpty())
                            <tr class="is-child-row hidden bg-slate-50/40" data-table-row-target="{{ $rowKey }}">
                                <td colspan="7" class="px-0 py-0">
                                    <div class="relative py-3 pl-14 pr-5">
                                        <span class="absolute left-6 top-0 h-1/2 border-l border-slate-300"></span>
                                        <span class="absolute left-6 top-1/2 w-8 border-t border-slate-300"></span>

                                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                                            <table class="w-full table-fixed text-sm">
                                                <colgroup>
                                                    <col class="w-[30%]">
                                                    <col class="w-[15%]">
                                                    <col class="w-[10%]">
                                                    <col class="w-[23%]">
                                                    <col class="w-[14%]">
                                                    <col class="w-[8%]">
                                                </colgroup>
                                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                                    <tr>
                                                        <th class="px-5 py-3 text-left font-semibold">Instruksi Kerja</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Nomor Dokumen</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Revisi</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Department</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Proses/Fungsi</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Tgl Terbit</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($instructions as $instruction)
                                                        <tr>
                                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $instruction['name'] }}</td>
                                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $instruction['number'] ?: '-' }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['revision'] }}</td>
                                                            <td class="px-5 py-4 text-slate-600">
                                                                {{ $instruction['departments']->isNotEmpty() ? $instruction['departments']->join(', ') : '-' }}
                                                            </td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['business_function'] }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['published_at'] }}</td>
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
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada dokumen yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </x-ui.scrollable-table>

            @if ($overviewRows->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $overviewRows->links() }}
                </div>
            @endif
        </x-ui.panel>
    </div>
</x-layouts::app>
