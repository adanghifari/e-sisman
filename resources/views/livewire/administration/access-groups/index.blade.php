<div class="space-y-6">
    <x-ui.page-header
        title="Group Akses"
        description="Kelola grup role, akses menu, dan anggota user."
    />

    <x-ui.panel
        title="List Group Akses"
        description="Menampilkan {{ $roles->count() }} data dari total {{ $roles->total() }} group akses."
        :padded="false"
    >
        <x-slot:actions>
            <x-ui.action-button type="button" wire:click="create" class="gap-2">
                <flux:icon name="plus" class="size-4" />
                Tambah Group
            </x-ui.action-button>
        </x-slot:actions>

        <div class="border-b border-slate-200 p-5">
            <x-ui.input
                label="Cari Group"
                name="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama group..."
            />
        </div>

        <x-ui.scrollable-table max-height="620px" min-width="860px">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Group</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Akses</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">User</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($roles as $role)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $role->nama_role }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $role->permissions_count }} akses</td>
                        <td class="px-5 py-4 text-slate-600">{{ $role->users_count }} user</td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <x-ui.icon-button
                                    icon="pencil"
                                    label="Edit group akses"
                                    size="sm"
                                    wire:click="edit({{ $role->id }})"
                                />

                                <x-ui.icon-button
                                    icon="trash"
                                    label="Hapus group akses"
                                    size="sm"
                                    wire:click="confirmDelete({{ $role->id }})"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada group akses yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $roles->links() }}
        </div>
    </x-ui.panel>

    @if ($showForm)
        <x-ui.modal
            :title="$editingId ? 'Edit Group Akses' : 'Tambah Group Akses'"
            :description="$formStep === 'detail' ? 'Lengkapi nama group dan pilih anggota user.' : 'Pilih akses yang dimiliki group ini.'"
            close-action="cancel"
            max-width="7xl"
            :clip="$formStep !== 'detail'"
        >
            @if ($formStep === 'detail')
                <div class="space-y-6 px-8 py-6">
                    <x-ui.form-input
                        label="Nama Group"
                        name="nama_role"
                        wire:model="nama_role"
                        placeholder="Contoh: Document Controller"
                    />

                    <div>
                        <span class="mb-2 block text-base font-medium text-slate-500">User</span>
                        <x-ui.user-search-select
                            name="access_group_user_picker"
                            :users="$users"
                            placeholder="Cari dan pilih user"
                            wire:model.live="selectedUserId"
                            wire:change="addUserFromPicker($event.target.value)"
                            data-clear-on-select="true"
                            data-access-group-user-picker
                        />
                    </div>

                    <div class="rounded-lg border border-slate-200">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-800">User Terpilih</h3>
                        </div>

                        <div class="max-h-80 space-y-2 overflow-y-auto p-4">
                            @forelse ($selectedUsers as $user)
                                <div class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $user->jabatan ?: $user->email }}</p>
                                    </div>

                                    <x-ui.icon-button
                                        icon="x-mark"
                                        label="Hapus user"
                                        variant="ghost"
                                        size="sm"
                                        wire:click="removeUser({{ $user->id }})"
                                    />
                                </div>
                            @empty
                                <p class="py-6 text-center text-sm text-slate-500">Belum ada user yang dipilih.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-ui.action-button type="button" variant="secondary" wire:click="manageAccess">
                            Manage Akses
                        </x-ui.action-button>
                    </div>
                </div>

                <x-ui.modal-step-footer
                    secondary-action="cancel"
                    primary-action="save"
                />
            @else
                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 overflow-y-auto px-8 py-6">
                        <div class="max-h-[58vh] space-y-4 overflow-y-auto rounded-lg border border-slate-200 p-4">
                            @foreach ($permissionsByModule as $module => $permissions)
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $module }}</p>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($permissions as $permission)
                                            <label class="flex items-start gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $permission->id }}"
                                                    wire:model="permissionIds"
                                                    class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                >
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-semibold text-slate-700">{{ $permission->name }}</span>
                                                    <span class="block break-words text-xs text-slate-500">{{ $permission->code }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <x-ui.modal-step-footer
                        align="between"
                        secondary-label="Back"
                        secondary-action="backToDetails"
                        primary-type="submit"
                    />
                </form>
            @endif
        </x-ui.modal>
    @endif

    @if ($showDeleteModal)
        <x-ui.confirm-modal
            title="Hapus Group Akses"
            description="Group yang masih dipakai approval tidak bisa dihapus."
            message="Yakin ingin menghapus group akses ini?"
            confirm-action="delete"
            cancel-action="cancelDelete"
            error-key="delete"
        />
    @endif
</div>
