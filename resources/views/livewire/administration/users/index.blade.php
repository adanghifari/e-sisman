<div class="space-y-6">
    <x-ui.page-header
        title="User"
        description="Kelola data user, role, jabatan, dan department pengguna."
    />

    <x-ui.panel
        title="List User"
        description="Menampilkan {{ $users->count() }} data dari total {{ $users->total() }} user."
        :padded="false"
    >
        <div class="border-b border-slate-200 p-5">
            <div class="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_repeat(3,minmax(170px,220px))]">
                <x-ui.input
                    label="Cari User"
                    name="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari user, jabatan, NIK, atau email..."
                />

                <x-ui.select
                    label="Role"
                    name="role"
                    wire:model.live="role"
                    :options="$roleOptions"
                />

                <x-ui.select
                    label="Department"
                    name="department"
                    wire:model.live="department"
                    :options="$departmentOptions"
                />

                <x-ui.select
                    label="Status"
                    name="status"
                    wire:model.live="status"
                    :options="$statusOptions"
                />
            </div>
        </div>

        <x-ui.scrollable-table max-height="620px" min-width="1120px">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">User</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Role</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jabatan</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Department</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">No. Whatsapp</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-slate-200 text-sm font-bold text-slate-500">
                                    {{ $user->initials() }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-slate-900">{{ $user->name }}</span>
                                    <span class="mt-0.5 block truncate text-sm text-slate-400">{{ $user->nik ?: $user->email }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            {{ $user->roles->pluck('nama_role')->implode(', ') ?: '-' }}
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            {{ $user->jabatan ?: '-' }}
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            {{ $user->department?->nama_department ?: '-' }}
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            {{ $user->no_whatsapp ?: '-' }}
                        </td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge label="Active" tone="sky" />
                        </td>
                        <td class="px-5 py-4">
                            <x-ui.icon-button
                                icon="pencil"
                                label="Edit user"
                                size="sm"
                                disabled
                                class="opacity-60"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada user yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $users->links() }}
        </div>
    </x-ui.panel>
</div>
