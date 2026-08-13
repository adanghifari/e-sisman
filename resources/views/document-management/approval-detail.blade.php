<x-layouts::app :title="__('Detail Approval Dokumen')">
    @php
        $levelKey = $document->documentLevel?->kode ?? 'level-3';
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
        ];
        $isLevelOne = $levelKey === 'level-1';
        $ownerLabel = $isLevelOne ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
        $statusCode = $activeApproval?->status?->kode_status ?? $document->status?->nama_status ?? '-';
        $readonlyInput = 'h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600 outline-none';
        $readonlySelect = 'h-12 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600 outline-none';
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.inbox') }}" class="transition hover:text-sky-700" wire:navigate>Inbox Approval</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $document->nomor_dokumen ?: 'Detail Dokumen' }}</span>
        </nav>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                    Detail Dokumen Level {{ $levelNumbers[$levelKey] ?? '-' }}
                </h1>
                <p class="mt-2 text-base font-medium text-slate-500">{{ $document->nama_dokumen }}</p>
            </div>

            <x-ui.status-badge :label="$statusCode" :tone="$statusCode === 'PENDING' ? 'sky' : ($statusCode === 'APPROVED' ? 'emerald' : 'red')" class="mt-1" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
            <div class="space-y-6">
                <x-documents.form-section title="Informasi Dokumen">
                    <dl class="divide-y divide-slate-100 px-6 py-4">
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Nama Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->nama_dokumen }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->documentLevel?->nama_level }} : {{ \Illuminate\Support\Str::after($document->documentLevel?->nama_dokumen ?? '', ': ') }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Proses Bisnis</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->businessProcess?->nama_proses_bisnis ?? '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->businessFunction?->nama_proses_fungsi ?? '-' }}</dd>
                        </div>
                    </dl>
                </x-documents.form-section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">{{ $ownerLabel }}</h2>
                    </div>

                    <div class="space-y-4 px-6 py-6">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pengisi Form</span>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                    {{ $document->creator?->initials() ?? '-' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->creator?->name ?? '-' }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->creator?->jabatan ?: $document->creator?->email }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Penyusun Resmi</span>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">
                                    {{ $document->officialPreparer?->initials() ?? '-' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->officialPreparer?->name ?? '-' }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->officialPreparer?->jabatan ?: $document->officialPreparer?->email }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <x-documents.form-section title="Isi Dokumen" icon="document-text">
                    <div class="space-y-4 px-6 py-6">
                        @forelse ($contentFiles as $file)
                            <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                        <p class="text-xs font-medium text-slate-500">{{ strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                    </div>
                                    <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Buka
                                    </a>
                                </div>

                                <iframe
                                    src="{{ route('documents.approval.files.preview', [$document, $file]) }}#view=FitH&navpanes=0"
                                    class="min-h-[760px] w-full bg-white xl:h-[82vh]"
                                ></iframe>
                            </section>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                Belum ada file isi dokumen.
                            </p>
                        @endforelse
                    </div>
                </x-documents.form-section>

                <x-documents.form-section title="Lampiran" icon="paper-clip">
                    <div class="space-y-3 px-6 py-6">
                        @forelse ($attachmentFiles as $file)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                    <p class="text-xs font-medium text-slate-500">{{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB</p>
                                </div>
                                <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Buka
                                </a>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                Tidak ada lampiran.
                            </p>
                        @endforelse
                    </div>
                </x-documents.form-section>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-8">
                <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <input type="text" value="{{ $document->nomor_dokumen ?: '-' }}" readonly class="{{ $readonlyInput }}">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input type="text" value="{{ str_pad((string) $document->nomor_revisi, 2, '0', STR_PAD_LEFT) }}.00" readonly class="{{ $readonlyInput }}">
                        </label>

                        <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <flux:icon name="arrow-path" class="size-6 text-slate-700" />
                                <span>Status Dokumen</span>
                                <span class="ml-auto rounded-full bg-slate-200 px-3 py-1 text-sm font-bold text-slate-700">{{ $document->status?->nama_status ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar-days" class="size-6 text-slate-700" />
                                <span>Tanggal Pengajuan</span>
                                <span class="ml-auto text-slate-500">{{ $document->submitted_at?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar" class="size-6 text-slate-700" />
                                <span>Tanggal Terbit</span>
                                <span class="ml-auto text-slate-500">{{ $document->tanggal_terbit?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($activeApproval)
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Keputusan Approval</h2>
                        </div>
                        <div class="grid gap-3 px-6 py-5 sm:grid-cols-2">
                            <form method="POST" action="{{ route('documents.approval.approve', $document) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-emerald-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    Approve
                                </button>
                            </form>
                            <button type="button" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-red-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-red-700" data-reject-modal-open>
                                Tolak
                            </button>
                        </div>
                    </section>
                @endif

                <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Assign Approver</h2>
                        <p class="mt-2 text-sm font-medium text-slate-500">
                            Approval Flow {{ $document->documentLevel?->nama_dokumen ?? $document->documentLevel?->nama_level ?? '-' }}
                        </p>
                    </div>

                    @if ($approvalFlowStages->isEmpty())
                        <div class="px-6 py-6">
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                                Belum ada aturan tahap approval.
                            </p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('documents.approval.assign', $document) }}" class="space-y-5 px-6 py-6" data-approver-assignment-form>
                            @csrf

                            @foreach ($approvalFlowStages as $stage)
                                @php
                                    $stageLabel = $stage->display_label ?: 'Approval';
                                    $oldApproverIds = collect(old("stage_approvers.{$stage->id}", []))
                                        ->filter()
                                        ->map(fn ($userId) => (int) $userId)
                                        ->values();
                                    $stageApprovers = $oldApproverIds->isNotEmpty()
                                        ? $assignableUsers->whereIn('id', $oldApproverIds)->values()
                                        : $document->approvals
                                            ->filter(fn ($approval) => $approval->stages === $stageLabel && $approval->responded_at === null)
                                            ->map(fn ($approval) => $approval->approver)
                                            ->filter()
                                            ->values();
                                    if (
                                        $stage->stage_order === 1
                                        && $stageApprovers->isEmpty()
                                        && $document->officialPreparer
                                        && data_get(old('stage_approvers', []), $stage->id) === null
                                    ) {
                                        $stageApprovers = collect([$document->officialPreparer]);
                                    }
                                @endphp

                                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-approver-stage="{{ $stage->id }}">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">
                                            {{ $stage->stage_order }}
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-slate-900">{{ $stage->keterangan ?: 'Tahap Approval' }}</h3>
                                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ $stage->nama_tahap }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3" data-approver-slots>
                                        @foreach ($stageApprovers as $approver)
                                            <div class="flex items-start gap-2" data-approver-slot>
                                                <div class="min-w-0 flex-1">
                                                    <x-ui.user-search-select
                                                        name="stage_approvers[{{ $stage->id }}][]"
                                                        :users="$assignableUsers"
                                                        :selected-user="$approver"
                                                        placeholder="Cari dan pilih approver"
                                                        required
                                                    />
                                                </div>
                                                <x-ui.icon-button
                                                    type="button"
                                                    icon="trash"
                                                    label="Hapus approver"
                                                    variant="ghost"
                                                    data-remove-approver-slot
                                                />
                                            </div>
                                        @endforeach
                                    </div>

                                    @error("stage_approvers.{$stage->id}")
                                        <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror

                                    <button type="button" class="mt-4 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-add-approver-slot>
                                        <flux:icon name="plus" class="size-5" />
                                        Tambah Approver
                                    </button>
                                </article>
                            @endforeach

                            <x-ui.action-button type="submit" class="w-full">
                                Save Approver
                            </x-ui.action-button>
                        </form>
                    @endif

                    <div class="border-t border-slate-100 px-6 py-5">
                        <h3 class="text-sm font-bold text-slate-900">Riwayat Approver</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($document->approvals->sortByDesc('assigned_at') as $approval)
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $approval->approver?->name ?? '-' }}</p>
                                        <x-ui.status-badge :label="$approval->status?->nama_status ?? '-'" :tone="$approval->status?->kode_status === 'APPROVED' ? 'emerald' : ($approval->status?->kode_status === 'REJECTED' ? 'red' : 'sky')" />
                                    </div>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $approval->stages ?: 'Approval' }}</p>
                                    @if ($approval->catatan)
                                        <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $approval->catatan }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-reject-modal>
        <form method="POST" action="{{ route('documents.approval.reject', $document) }}" class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
            @csrf
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Alasan Penolakan</h2>
                    <p class="mt-1 text-sm text-slate-500">Isi catatan agar pengaju memahami bagian yang perlu diperbaiki.</p>
                </div>
                <button type="button" class="text-slate-400 transition hover:text-slate-700" data-reject-modal-close>
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>
            <div class="px-6 py-5">
                <x-ui.textarea
                    label="Catatan"
                    name="catatan"
                    rows="5"
                    placeholder="Tulis alasan penolakan..."
                    required
                />
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <x-ui.action-button type="button" variant="secondary" data-reject-modal-close>
                    Batal
                </x-ui.action-button>
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700">
                    Tolak Dokumen
                </button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-reject-modal]');

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-reject-modal-open]')) {
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                }

                if (event.target.closest('[data-reject-modal-close]')) {
                    modal?.classList.add('hidden');
                    modal?.classList.remove('flex');
                }
            });
        })();
    </script>

    <template data-approver-slot-template>
        <div class="flex items-start gap-2" data-approver-slot>
            <div class="min-w-0 flex-1">
                <x-ui.user-search-select
                    name="__NAME__"
                    :users="$assignableUsers"
                    placeholder="Cari dan pilih approver"
                    required
                />
            </div>
            <x-ui.icon-button
                type="button"
                icon="trash"
                label="Hapus approver"
                variant="ghost"
                data-remove-approver-slot
            />
        </div>
    </template>

    <script>
        (() => {
            const template = document.querySelector('[data-approver-slot-template]');

            document.addEventListener('click', (event) => {
                const addButton = event.target.closest('[data-add-approver-slot]');

                if (addButton) {
                    const stage = addButton.closest('[data-approver-stage]');
                    const list = stage?.querySelector('[data-approver-slots]');

                    if (!stage || !list || !template) {
                        return;
                    }

                    const slot = template.innerHTML.replaceAll('__NAME__', `stage_approvers[${stage.dataset.approverStage}][]`);
                    list.insertAdjacentHTML('beforeend', slot);
                    return;
                }

                const removeButton = event.target.closest('[data-remove-approver-slot]');

                if (removeButton) {
                    removeButton.closest('[data-approver-slot]')?.remove();
                }
            });
        })();
    </script>
</x-layouts::app>
