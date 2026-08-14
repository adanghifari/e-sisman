<x-layouts::app :title="__('Detail Dokumen Master')">
    @php
        $levelKey = $document->documentLevel?->kode ?? 'level-3';
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
        ];
        $isObsolete = $document->status?->nama_status === \App\Models\StatusDocument::OBSOLETE;
        $ownerLabel = $levelKey === 'level-1' ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
        $publishedAt = $document->tanggal_terbit ?? $document->approved_at;
        $levelTitle = trim(($document->documentLevel?->nama_level ?? '').' : '.\Illuminate\Support\Str::after($document->documentLevel?->nama_dokumen ?? '', ': '), ' :');
        $processLabel = collect([
            $document->businessProcess?->nama_proses_bisnis,
            $document->businessFunction?->nama_proses_fungsi,
        ])->filter()->implode(' / ');
        $readonlyInput = 'h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600 outline-none';
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.master') }}" class="transition hover:text-sky-700" wire:navigate>Dokumen Master</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $document->nomor_dokumen ?: 'Detail Dokumen' }}</span>
        </nav>

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                    Detail Dokumen Master
                </h1>
                <p class="mt-2 text-base font-medium text-slate-500">{{ $document->nama_dokumen }}</p>
            </div>

            <x-ui.status-badge :label="$isObsolete ? 'Obsolete' : 'Master'" :tone="$isObsolete ? 'red' : 'sky'" class="mt-1" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
            <div class="space-y-6">
                <x-documents.form-section title="Informasi Dokumen">
                    <dl class="divide-y divide-slate-100 px-6 py-4">
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Nama Dokumen</dt>
                            <dd class="text-sm font-bold uppercase leading-6 text-slate-900">{{ $document->nama_dokumen }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $levelTitle ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Nomor Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->nomor_dokumen ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $processLabel ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}</dd>
                        </div>
                        @if ($document->referenceDocument)
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Dokumen Acuan</dt>
                                <dd class="text-sm font-bold text-slate-900">
                                    {{ $document->referenceDocument->nomor_dokumen ?: '-' }} - {{ $document->referenceDocument->nama_dokumen }}
                                </dd>
                            </div>
                        @endif
                        @if ($document->revisedFrom)
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Revisi Dari</dt>
                                <dd class="text-sm font-bold text-slate-900">
                                    {{ $document->revisedFrom->nomor_dokumen ?: '-' }} - Revisi {{ $document->revisedFrom->formatted_revision }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-documents.form-section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">{{ $ownerLabel }}</h2>
                    </div>

                    <div class="grid gap-4 px-6 py-6 md:grid-cols-2">
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
                                    <a href="{{ route('documents.master.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Buka
                                    </a>
                                </div>

                                <iframe
                                    src="{{ route('documents.master.files.preview', [$document, $file]) }}#view=FitH&navpanes=0"
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
                                <a href="{{ route('documents.master.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
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
                            <input type="text" value="{{ $document->formatted_revision }}" readonly class="{{ $readonlyInput }}">
                        </label>

                        <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <flux:icon name="document-check" class="size-6 text-slate-700" />
                                <span>Stamp</span>
                                <x-ui.status-badge :label="$isObsolete ? 'Obsolete' : 'Master'" :tone="$isObsolete ? 'red' : 'sky'" class="ml-auto" />
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar-days" class="size-6 text-slate-700" />
                                <span>Tanggal Pengajuan</span>
                                <span class="ml-auto text-slate-500">{{ $document->submitted_at?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar" class="size-6 text-slate-700" />
                                <span>Tanggal Terbit</span>
                                <span class="ml-auto text-slate-500">{{ $publishedAt?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <h3 class="text-sm font-bold text-slate-900">Riwayat Approver</h3>
                    </div>
                    <div class="space-y-2 px-6 py-5">
                        @forelse ($document->approvals->sortByDesc('assigned_at') as $approval)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="truncate text-sm font-semibold text-slate-800">{{ $approval->approver?->name ?? '-' }}</p>
                                    <x-ui.status-badge :label="$approval->status?->kode_status ?? '-'" :tone="$approval->status?->kode_status === \App\Models\ApprovalStatus::APPROVED ? 'emerald' : ($approval->status?->kode_status === \App\Models\ApprovalStatus::REJECTED ? 'red' : 'sky')" />
                                </div>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ $approval->stages ?: 'Approval' }}</p>
                                @if ($approval->catatan)
                                    <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $approval->catatan }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                                Belum ada riwayat approver.
                            </p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
