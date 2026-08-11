<x-layouts::app :title="__('Department')">
    @php
        $filters = [
            'search' => trim((string) request('search', '')),
            'status' => (string) request('status', ''),
        ];

        $departments = [
            ['name' => 'Stevedoring Operation Department', 'status' => 'Active'],
            ['name' => 'Market Outreach & Portofolio Division', 'status' => 'Active'],
            ['name' => 'Handling Operation Department', 'status' => 'Active'],
            ['name' => 'HSSE Department', 'status' => 'Active'],
            ['name' => 'Outreach General Marketing Department', 'status' => 'Active'],
            ['name' => 'Corporate Secretary & PFSO Division', 'status' => 'Active'],
            ['name' => 'Quality Assurance Department', 'status' => 'Active'],
            ['name' => 'Teknologi Informasi Department', 'status' => 'Inactive'],
        ];

        $statusOptions = [
            '' => 'Semua Status',
            'Active' => 'Active',
            'Inactive' => 'Inactive',
        ];

        $filteredDepartments = collect($departments)
            ->filter(fn ($department) => $filters['search'] === '' || str_contains(strtolower($department['name']), strtolower($filters['search'])))
            ->filter(fn ($department) => $filters['status'] === '' || $department['status'] === $filters['status'])
            ->values();
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Department" />

        <x-ui.panel
            title="List Department"
            description="Menampilkan {{ $filteredDepartments->count() }} department dari total {{ count($departments) }} department."
            :padded="false"
        >
            <x-slot:actions>
                <x-ui.action-button type="button" class="gap-2">
                    <flux:icon name="plus" class="size-4" />
                    Tambah Data
                </x-ui.action-button>
            </x-slot:actions>

            <form method="GET" action="{{ route('master-data.departments') }}" class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-[minmax(240px,1fr)_minmax(180px,260px)_auto] md:items-end">
                <x-ui.input
                    label="Cari Department"
                    name="search"
                    :value="$filters['search']"
                    placeholder="Cari Department"
                />

                <x-ui.select
                    label="Status"
                    name="status"
                    :value="$filters['status']"
                    :options="$statusOptions"
                />

                <div class="flex gap-2">
                    <x-ui.action-button type="submit">
                        Terapkan
                    </x-ui.action-button>
                    <x-ui.action-button :href="route('master-data.departments')" variant="secondary">
                        Reset
                    </x-ui.action-button>
                </div>
            </form>

            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[68%]">
                    <col class="w-[17%]">
                    <col class="w-[15%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Department</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredDepartments as $department)
                        <tr class="border-b border-slate-100">
                            <td class="px-5 py-5 font-medium text-slate-800">{{ $department['name'] }}</td>
                            <td class="px-5 py-5">
                                <x-ui.status-badge
                                    :label="$department['status']"
                                    :tone="$department['status'] === 'Active' ? 'sky' : 'slate'"
                                    class="rounded-full px-3 py-1.5 text-sm"
                                />
                            </td>
                            <td class="px-5 py-5">
                                <x-ui.icon-button icon="pencil" label="Edit department" variant="ghost" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada department yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts::app>
