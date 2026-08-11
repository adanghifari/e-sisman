<div class="space-y-6">
    <x-ui.page-header title="Proses Bisnis" />

    <x-ui.filter-bar wire:submit.prevent>
        <x-ui.input
            label="Cari"
            name="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari kode atau proses bisnis..."
        />

        <x-ui.select
            label="Status"
            name="status"
            wire:model.live="status"
            :value="$status"
            :options="$statusOptions"
        />

        <div class="flex items-end">
            <button type="button" wire:click="create" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                Tambah Data
            </button>
        </div>
    </x-ui.filter-bar>

    <x-ui.panel
        title="List Proses Bisnis"
        description="Menampilkan {{ $businessProcesses->count() }} data dari total {{ $businessProcesses->total() }} proses bisnis."
        :padded="false"
    >
        <x-ui.scrollable-table max-height="620px" min-width="760px">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Kode</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Proses Bisnis</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($businessProcesses as $businessProcess)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $businessProcess->kode }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $businessProcess->nama_proses_bisnis }}</td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge
                                :label="$businessProcess->is_active ? 'Active' : 'Inactive'"
                                :tone="$businessProcess->is_active ? 'sky' : 'slate'"
                            />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <x-ui.icon-button
                                    icon="pencil"
                                    label="Edit proses bisnis"
                                    size="sm"
                                    wire:click="edit({{ $businessProcess->id }})"
                                />

                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $businessProcess->id }})"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    aria-label="{{ $businessProcess->is_active ? 'Nonaktifkan proses bisnis' : 'Aktifkan proses bisnis' }}"
                                >
                                    <span>{{ $businessProcess->is_active ? 'Active' : 'Inactive' }}</span>
                                    <span class="relative h-5 w-9 rounded-full transition {{ $businessProcess->is_active ? 'bg-sky-600' : 'bg-slate-300' }}">
                                        <span class="absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow-sm transition {{ $businessProcess->is_active ? 'translate-x-4' : '' }}"></span>
                                    </span>
                                </button>

                                <x-ui.icon-button
                                    icon="trash"
                                    label="Hapus proses bisnis"
                                    size="sm"
                                    wire:click="confirmDelete({{ $businessProcess->id }})"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada proses bisnis yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $businessProcesses->links() }}
        </div>
    </x-ui.panel>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-6">
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ $editingId ? 'Edit Proses Bisnis' : 'Tambah Proses Bisnis' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Lengkapi kode, nama, dan status proses bisnis.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cancel"
                        class="grid size-9 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Tutup form"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-5 px-6 py-5">
                    <div class="grid gap-4 md:grid-cols-[160px_1fr]">
                        <div>
                            <label for="kode" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kode</label>
                            <input
                                id="kode"
                                type="text"
                                wire:model="kode"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                placeholder="Contoh: MRI"
                            >
                            @error('kode')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="nama_proses_bisnis" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Proses Bisnis</label>
                            <input
                                id="nama_proses_bisnis"
                                type="text"
                                wire:model="nama_proses_bisnis"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                placeholder="Nama proses bisnis"
                            >
                            @error('nama_proses_bisnis')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Status</p>
                            <p class="text-xs text-slate-500">{{ $is_active ? 'Data aktif dan bisa dipilih di dokumen.' : 'Data nonaktif tidak ditampilkan sebagai pilihan aktif.' }}</p>
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-3">
                            <span class="text-sm font-semibold text-slate-700">{{ $is_active ? 'Active' : 'Inactive' }}</span>
                            <input type="checkbox" wire:model.live="is_active" class="peer sr-only">
                            <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-sky-600">
                                <span class="absolute left-1 top-1 size-4 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-5">
                        <button type="button" wire:click="cancel" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-6">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900">Hapus Proses Bisnis</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Data yang belum digunakan dokumen akan dihapus permanen.
                    </p>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <p class="text-sm text-slate-700">
                        Yakin ingin menghapus proses bisnis ini?
                    </p>

                    @error('delete')
                        <div class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" wire:click="cancelDelete" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>

                    <button type="button" wire:click="delete" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
