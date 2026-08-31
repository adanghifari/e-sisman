<x-layouts::app :title="__('Detail Approval Dokumen')">
    @php
        $levelKey = $document->documentLevel?->kode ?? 'level-3';
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
            'level-4' => 'IV',
        ];
        $isLevelOne = $levelKey === 'level-1';
        $statusCode = $activeApproval?->status?->kode_status ?? $document->status?->nama_status ?? '-';
        $statusLabel = $activeApproval?->status?->nama_status ?? $document->status?->nama_status ?? '-';
        $isObsoleteRequest = $document->request_type === 'obsolete';
        $ownerLabel = $isObsoleteRequest ? 'Pengaju Awal Dokumen' : ($isLevelOne ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses');
        $contentSectionTitle = match (true) {
            $isObsoleteRequest => 'Dokumen yang Akan Diobsoletekan',
            $levelKey === 'level-4' => 'Dokumen Revisi',
            default => 'Isi Dokumen',
        };
        $approvalFlowLabel = $approvalFlowDocumentLevel?->nama_dokumen
            ?? $approvalFlowDocumentLevel?->nama_level
            ?? $document->documentLevel?->nama_dokumen
            ?? $document->documentLevel?->nama_level
            ?? '-';
        $approvalFlowDescription = $levelKey === 'level-4' && $document->revisedFrom?->documentLevel
            ? 'Mengikuti approval flow dokumen induk: '.$approvalFlowLabel
            : 'Approval Flow '.$approvalFlowLabel;
        $contentFileLabels = [
            'filled_template' => 'Template Dokumen',
            'imported_document' => 'Dokumen Import',
            'revision_content' => 'Isi Dokumen Versi Revisi',
            'revision_form' => 'Lembar Revisi',
            'revision_before' => 'Semula',
            'revision_after' => 'Menjadi',
        ];
        $revisionMainFiles = $levelKey === 'level-4'
            ? collect(['revision_content', 'revision_form'])
                ->map(fn ($type) => $contentFiles
                    ->where('type_file', $type)
                    ->sortByDesc('id')
                    ->first())
                ->filter()
                ->values()
            : collect();
        $otherContentFiles = $levelKey === 'level-4'
            ? $contentFiles
                ->reject(fn ($file) => in_array($file->type_file, ['revision_content', 'revision_form'], true))
                ->values()
            : $contentFiles;
        $generatedPrintoutStatus = $generatedPrintout?->generation_status;
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

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                    {{ $isObsoleteRequest ? 'Pengajuan Obsolete Dokumen' : 'Detail Dokumen Level '.($levelNumbers[$levelKey] ?? '-') }}
                </h1>
                <p class="mt-2 text-base font-medium text-slate-500">{{ $document->nama_dokumen }}</p>
            </div>

            <x-ui.status-badge :label="$statusLabel" :tone="$statusCode === 'PENDING' ? 'amber' : ($statusCode === 'APPROVED' ? 'emerald' : 'red')" class="mt-1" />
        </div>

        @if ($isObsoleteRequest)
            <section class="rounded-lg border border-red-100 bg-red-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-red-600 ring-1 ring-red-100">
                        <flux:icon name="archive-box-x-mark" class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-red-950">Review Pengajuan Obsolete</h2>
                        <p class="mt-1 text-sm font-medium leading-6 text-red-800">
                            Approval ini akan mengubah dokumen master terkait menjadi obsolete setelah seluruh tahap disetujui.
                        </p>
                    </div>
                </div>
            </section>
        @endif

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
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $isObsoleteRequest ? 'Pengaju Awal Dokumen' : 'Pengisi Form' }}</span>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                    {{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->initials() ?? '-' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->name ?? '-' }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->jabatan ?: (($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->email ?? '-') }}</span>
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

                        @if ($isObsoleteRequest)
                            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-3">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-red-700">Pengaju Obsolete</span>
                                <div class="mt-2 flex items-center gap-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-red-700 ring-1 ring-red-100">
                                        {{ $document->creator?->initials() ?? '-' }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->creator?->name ?? '-' }}</span>
                                        <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->creator?->jabatan ?: $document->creator?->email }}</span>
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                </section>

                @if ($isObsoleteRequest)
                    <x-documents.form-section title="Alasan Obsolete" icon="archive-box-x-mark">
                        <div class="px-6 py-6">
                            <div class="rounded-lg border border-red-100 bg-red-50 px-4 py-4">
                                <p class="text-sm font-semibold leading-6 text-red-900">
                                    {{ $document->catatan_revisi ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </x-documents.form-section>
                @endif

                @if (! $isObsoleteRequest)
                    <x-documents.form-section title="Printout PDF Sementara" icon="document-check">
                        <div class="space-y-4 px-6 py-6">
                            @if ($generatedPrintoutStatus === \App\Models\DocumentFinalArtifact::STATUS_GENERATED)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $generatedPrintout->generated_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">Generated saat submit. Lembar pengesahan akan tersedia setelah semua approval selesai.</p>
                                        </div>
                                        <a href="{{ route('documents.approval.generated.show', [$document, $generatedPrintout]) }}" target="_blank" class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Buka
                                        </a>
                                    </div>

                                    <iframe
                                        src="{{ route('documents.approval.generated.show', [$document, $generatedPrintout]) }}#view=FitH&navpanes=0"
                                        class="min-h-[760px] w-full bg-white xl:h-[82vh]"
                                    ></iframe>
                                </section>
                            @elseif ($generatedPrintoutStatus === \App\Models\DocumentFinalArtifact::STATUS_FAILED)
                                <p class="rounded-lg border border-red-100 bg-red-50 px-4 py-4 text-sm font-semibold leading-6 text-red-800">
                                    Printout PDF sementara gagal digenerate. {{ $generatedPrintout->generation_error ?: 'Silakan cek file sumber dokumen.' }}
                                </p>
                            @else
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Printout PDF sementara belum tersedia.
                                </p>
                            @endif
                        </div>
                    </x-documents.form-section>
                @endif

                <x-documents.form-section :title="$contentSectionTitle" icon="document-text">
                    <div class="space-y-4 px-6 py-6">
                        @if ($isObsoleteRequest)
                            @forelse ($obsoleteSourceContentFiles as $file)
                                @php
                                    $obsoleteSourceFileRoutePrefix = $document->revisedFrom?->status?->nama_status === \App\Models\StatusDocument::OBSOLETE
                                        ? 'documents.obsolete'
                                        : 'documents.master';
                                @endphp
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
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
                        @elseif ($levelKey === 'level-4')
                            @if ($revisionMainFiles->isNotEmpty())
                                <div class="grid gap-4 2xl:grid-cols-2">
                                    @foreach ($revisionMainFiles as $file)
                                        <section class="min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                            <div class="flex min-h-20 items-start justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold text-slate-900">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $file->original_file_name }}</p>
                                                </div>
                                                <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                                    Buka
                                                </a>
                                            </div>

                                            <iframe
                                                src="{{ route('documents.approval.files.preview', [$document, $file]) }}#view=FitH&navpanes=0"
                                                class="h-[620px] w-full bg-white 2xl:h-[72vh]"
                                            ></iframe>
                                        </section>
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($otherContentFiles as $file)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
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
                            @endforeach

                            @if ($revisionMainFiles->isEmpty() && $otherContentFiles->isEmpty())
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Belum ada file isi dokumen.
                                </p>
                            @endif
                        @else
                        @forelse ($contentFiles as $file)
                            <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                        <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
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
                        @endif
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
                            <input type="text" value="{{ $masterDisplayNumber }}" readonly class="{{ $readonlyInput }}">
                        </label>

                        @if ($document->nomor_lembar_revisi)
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nomor Lembar Revisi</span>
                                <input type="text" value="{{ $document->nomor_lembar_revisi }}" readonly class="{{ $readonlyInput }}">
                            </label>
                        @endif

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input type="text" value="{{ $document->formatted_revision }}" readonly class="{{ $readonlyInput }}">
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

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <h3 class="text-sm font-bold text-slate-900">Riwayat Approver</h3>
                    </div>
                    @php
                        $approvalStageOrders = $approvalFlowStages
                            ->mapWithKeys(fn ($stage) => [($stage->display_label ?: 'Approval') => $stage->stage_order]);
                        $approvalHistory = $document->approvals
                            ->reject(fn ($approval) => $approval->stages === 'TTD Penyusun Resmi')
                            ->sortBy(fn ($approval) => sprintf(
                                '%04d-%010d-%04d',
                                $approvalStageOrders->get($approval->stages, 9999),
                                $approval->assigned_at?->timestamp ?? 0,
                                $approval->id,
                            ))
                            ->values();
                        $approvalStatusLabels = [
                            \App\Models\ApprovalStatus::WAITING => 'Menunggu',
                            \App\Models\ApprovalStatus::PENDING => 'Dalam Review',
                            \App\Models\ApprovalStatus::APPROVED => 'Disetujui',
                            \App\Models\ApprovalStatus::REJECTED => 'Ditolak',
                            \App\Models\ApprovalStatus::TERMINATED => 'Dihentikan',
                        ];
                        $approvalStatusTones = [
                            \App\Models\ApprovalStatus::WAITING => 'sky',
                            \App\Models\ApprovalStatus::PENDING => 'amber',
                            \App\Models\ApprovalStatus::APPROVED => 'emerald',
                            \App\Models\ApprovalStatus::REJECTED => 'red',
                            \App\Models\ApprovalStatus::TERMINATED => 'slate',
                        ];
                    @endphp
                    <div class="space-y-2 px-6 py-5">
                        @forelse ($approvalHistory as $approval)
                            @php
                                $approvalStatusCode = $approval->status?->kode_status;
                                $stageOrder = $approvalStageOrders->get($approval->stages);
                                $approvalTimestamp = $approval->responded_at;
                            @endphp
                            <div class="rounded-lg bg-slate-50 px-3 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-100 text-xs font-bold text-sky-700">
                                            {{ $stageOrder ?? '-' }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-800">{{ $approval->approver?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs font-medium text-slate-500">
                                                {{ $approval->stages ?: 'Approval' }}
                                            </p>
                                            @if ($approvalTimestamp)
                                                <p class="mt-1 text-xs font-medium text-slate-500">
                                                    Diproses pada {{ $approvalTimestamp->translatedFormat('d M Y H:i:s') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <x-ui.status-badge
                                        :label="$approvalStatusLabels[$approvalStatusCode] ?? ($approval->status?->nama_status ?? '-')"
                                        :tone="$approvalStatusTones[$approvalStatusCode] ?? 'sky'"
                                        class="shrink-0"
                                    />
                                </div>
                                @if ($approval->catatan)
                                    <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $approval->catatan }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                                Belum ada approver yang disimpan.
                            </p>
                        @endforelse
                    </div>
                </section>

                <x-documents.history-section :document-history="$documentHistory" />

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

                @if ($canManageApproverAssignment)
                    <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Assign Approver</h2>
                            <p class="mt-2 text-sm font-medium text-slate-500">
                                {{ $approvalFlowDescription }}
                            </p>
                        </div>

                        @if ($approvalFlowStages->isEmpty())
                        <div class="px-6 py-6">
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                                Belum ada aturan tahap approval.
                            </p>
                        </div>
                    @else
                        @php
                            $hasSavedApprovers = $document->approvals
                                ->reject(fn ($approval) => $approval->stages === 'TTD Penyusun Resmi')
                                ->isNotEmpty();
                            $hasAssignmentErrors = collect($errors->getMessages())
                                ->keys()
                                ->contains(fn ($key) => $key === 'stage_approvers' || str_starts_with($key, 'stage_approvers.'));
                            $hasAssignmentValidationState = old('stage_approvers') !== null || $hasAssignmentErrors;
                            $assignmentStartsReadonly = $hasSavedApprovers && ! $hasAssignmentValidationState;
                        @endphp

                        <form
                            method="POST"
                            action="{{ route('documents.approval.assign', $document) }}"
                            class="space-y-5 px-6 py-6"
                            data-approver-assignment-form
                            data-readonly="{{ $assignmentStartsReadonly ? 'true' : 'false' }}"
                        >
                            @csrf

                            @if ($hasSavedApprovers)
                                <div class="flex justify-end" data-approver-readonly-action @class(['hidden' => ! $assignmentStartsReadonly])>
                                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-approver-edit-open>
                                        <flux:icon name="pencil-square" class="size-4" />
                                        Edit Approver
                                    </button>
                                </div>
                            @endif

                            @foreach ($approvalFlowStages as $stage)
                                @php
                                    $stageLabel = $stage->display_label ?: 'Approval';
                                    $stageApprovals = $document->approvals
                                        ->filter(fn ($approval) => $approval->stages === $stageLabel)
                                        ->values();
                                    $respondedApprovals = $stageApprovals
                                        ->filter(fn ($approval) => $approval->responded_at !== null)
                                        ->values();
                                    $isStageFullyApproved = $stageApprovals->isNotEmpty()
                                        && $stageApprovals->every(fn ($approval) => $approval->status?->kode_status === \App\Models\ApprovalStatus::APPROVED);
                                    $hasOldStageInput = data_get(old('stage_approvers', []), $stage->id) !== null;
                                    $oldApproverIds = collect(old("stage_approvers.{$stage->id}", []))
                                        ->filter()
                                        ->map(fn ($userId) => (int) $userId)
                                        ->values();
                                    $stageApprovers = $hasOldStageInput && ! $isStageFullyApproved
                                        ? $respondedApprovals
                                            ->map(fn ($approval) => [
                                                'user' => $approval->approver,
                                                'status' => $approval->status?->kode_status,
                                                'locked' => true,
                                            ])
                                            ->toBase()
                                            ->merge(
                                                $assignableUsers
                                                    ->whereIn('id', $oldApproverIds->diff($respondedApprovals->pluck('user_id')))
                                                    ->map(fn ($user) => [
                                                        'user' => $user,
                                                        'status' => null,
                                                        'locked' => false,
                                                    ])
                                                    ->values()
                                            )
                                            ->filter(fn ($item) => $item['user'])
                                            ->values()
                                        : $stageApprovals
                                            ->map(fn ($approval) => [
                                                'user' => $approval->approver,
                                                'status' => $approval->status?->kode_status,
                                                'locked' => $approval->responded_at !== null,
                                            ])
                                            ->toBase()
                                            ->filter(fn ($item) => $item['user'])
                                            ->values();
                                @endphp

                                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-approver-stage="{{ $stage->id }}">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">
                                            {{ $stage->stage_order }}
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-slate-900">{{ $stage->nama_tahap ?: 'Tahap Approval' }}</h3>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3" data-approver-slots>
                                        @foreach ($stageApprovers as $item)
                                            @php
                                                $approver = $item['user'];
                                                $approverStatus = $item['status'];
                                                $locked = $item['locked'];
                                            @endphp
                                            <div class="flex items-start gap-2" data-approver-slot>
                                                <div class="min-w-0 flex-1">
                                                    @if ($locked)
                                                        <input type="hidden" name="stage_approvers[{{ $stage->id }}][]" value="{{ $approver->id }}">
                                                        <div class="flex min-h-12 w-full items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600">
                                                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                                                                {{ $approver->initials() }}
                                                            </span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate font-semibold text-slate-800">{{ $approver->name }}</span>
                                                                <span class="block truncate text-xs text-slate-500">{{ $approver->jabatan ?: $approver->email }}</span>
                                                            </span>
                                                            <span class="shrink-0 rounded-md bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                                                {{ $approvalStatusLabels[$approverStatus] ?? 'Terkunci' }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <div data-approver-readonly-card @class(['hidden' => ! $assignmentStartsReadonly])>
                                                            <div class="flex min-h-12 w-full items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600">
                                                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100">
                                                                    {{ $approver->initials() }}
                                                                </span>
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate font-semibold text-slate-800">{{ $approver->name }}</span>
                                                                    <span class="block truncate text-xs text-slate-500">{{ $approver->jabatan ?: $approver->email }}</span>
                                                                </span>
                                                                @if ($approverStatus)
                                                                    <span class="shrink-0 rounded-md bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700">
                                                                        {{ $approvalStatusLabels[$approverStatus] ?? $approverStatus }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                                            <x-ui.user-search-select
                                                                name="stage_approvers[{{ $stage->id }}][]"
                                                                :users="$assignableUsers"
                                                                :selected-user="$approver"
                                                                placeholder="Cari dan pilih approver"
                                                                required
                                                            />
                                                        </div>
                                                    @endif
                                                </div>
                                                @if (! $locked)
                                                    <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                                        <x-ui.icon-button
                                                            type="button"
                                                            icon="trash"
                                                            label="Hapus approver"
                                                            variant="ghost"
                                                            data-remove-approver-slot
                                                        />
                                                    </div>
                                                @else
                                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400" title="Approver terkunci">
                                                        <flux:icon name="lock-closed" class="size-5" />
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @error("stage_approvers.{$stage->id}")
                                        <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror

                                    @if (! $isStageFullyApproved)
                                        <button type="button" class="mt-4 h-12 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 {{ $assignmentStartsReadonly ? 'hidden' : 'inline-flex' }}" data-add-approver-slot data-approver-edit-control>
                                            <flux:icon name="plus" class="size-5" />
                                            Tambah Approver
                                        </button>
                                    @endif
                                </article>
                            @endforeach

                            <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                <x-ui.action-button type="submit" class="w-full">
                                    Save Approver
                                </x-ui.action-button>
                            </div>
                        </form>
                        @endif
                    </section>
                @endif
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

    <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-approver-edit-modal>
        <div class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Edit Approver?</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Approver yang sudah memberikan respon akan tetap terkunci. Hanya approver yang belum merespon yang bisa diubah.
                    </p>
                </div>
                <button type="button" class="text-slate-400 transition hover:text-slate-700" data-approver-edit-close>
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <x-ui.action-button type="button" variant="secondary" data-approver-edit-close>
                    Batal
                </x-ui.action-button>
                <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700" data-approver-edit-confirm>
                    Ya, Edit
                </button>
            </div>
        </div>
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

    <script>
        (() => {
            const form = document.querySelector('[data-approver-assignment-form]');
            const modal = document.querySelector('[data-approver-edit-modal]');

            if (!form) {
                return;
            }

            const showModal = () => {
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            };

            const hideModal = () => {
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            };

            const enableEditMode = () => {
                form.dataset.readonly = 'false';
                form.querySelector('[data-approver-readonly-action]')?.classList.add('hidden');

                form.querySelectorAll('[data-approver-readonly-card]').forEach((element) => {
                    element.classList.add('hidden');
                });

                form.querySelectorAll('[data-approver-edit-control]').forEach((element) => {
                    element.classList.remove('hidden');

                    if (element.matches('[data-add-approver-slot]')) {
                        element.classList.add('inline-flex');
                    }
                });

                hideModal();
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-approver-edit-open]')) {
                    showModal();
                    return;
                }

                if (event.target.closest('[data-approver-edit-close]')) {
                    hideModal();
                    return;
                }

                if (event.target.closest('[data-approver-edit-confirm]')) {
                    enableEditMode();
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
            if (window.approverSlotManagerReady) {
                return;
            }

            window.approverSlotManagerReady = true;

            document.addEventListener('click', (event) => {
                const addButton = event.target.closest('[data-add-approver-slot]');

                if (addButton) {
                    const template = document.querySelector('[data-approver-slot-template]');
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
