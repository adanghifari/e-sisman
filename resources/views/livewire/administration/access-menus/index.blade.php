<div class="space-y-6">
    <x-ui.page-header
        title="Menu Akses"
        description="Katalog akses yang bisa diberikan ke group."
    />

    <x-ui.panel
        title="List Menu Akses"
        description="Menampilkan {{ $permissions->count() }} data dari total {{ $permissions->total() }} akses."
        :padded="false"
    >
        <div class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-[minmax(260px,1fr)_minmax(180px,260px)]">
            <x-ui.input
                label="Cari Akses"
                name="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode, nama, atau route..."
            />

            <x-ui.select
                label="Module"
                name="module"
                wire:model.live="module"
                :value="$module"
                :options="$moduleOptions"
            />
        </div>

        <x-ui.scrollable-table max-height="620px" min-width="920px">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Module</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Akses</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Kode</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Route</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($permissions as $permission)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 text-slate-600">{{ $permission->module }}</td>
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $permission->name }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $permission->code }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $permission->route ?: '-' }}</td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge :label="$permission->action" tone="sky" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada akses yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $permissions->links() }}
        </div>
    </x-ui.panel>
</div>
