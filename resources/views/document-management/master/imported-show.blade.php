<x-documents.detail-page
    title="Detail Dokumen Master"
    heading="Detail Dokumen Master"
    index-route="documents.master"
    index-label="Dokumen Master"
    :document="$document"
    :master-display-number="$masterDisplayNumber"
    :revision-request-display-number="$revisionRequestDisplayNumber"
    stamp-label="Master"
    stamp-tone="sky"
    file-route-prefix="documents.existing.imports"
    :approval-flow-stages="$approvalFlowStages"
    :content-files="$contentFiles"
    :attachment-files="$attachmentFiles"
    :document-history="collect()"
    :related-obsolete-documents="$relatedObsoleteDocuments"
    :show-owner-section="false"
    :show-approval-history="false"
    origin-notice="Dokumen ini berasal dari imported existing master sebelum go-live, sehingga riwayat approval V2 belum tersedia untuk versi awal ini."
>
    <x-slot:actions>
        @if ($canRequestRevision)
            <div class="border-t border-dashed border-slate-200 px-6 py-5">
                <form method="POST" action="{{ route('documents.existing.imports.revisions.store', $document) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <p class="text-sm font-bold text-slate-900">Ajukan Revisi</p>
                        <p class="mt-1 text-xs font-medium leading-5 text-slate-500">Revisi akan dibuat sebagai transaksi baru di workflow V2.</p>
                    </div>

                    <x-ui.input label="Nama Dokumen Revisi" name="nama_dokumen" :value="$document->nama_dokumen" />
                    <label class="block">
                        <span class="mb-2 block text-base font-medium text-slate-500">Penyusun Resmi</span>
                        <x-ui.user-search-select
                            name="official_preparer_id"
                            :users="$users"
                            placeholder="Pilih penyusun resmi"
                            required
                        />
                    </label>
                    <x-ui.date-input label="Tanggal Terbit Revisi" name="tanggal_terbit" />
                    <x-ui.textarea label="Catatan Revisi" name="catatan_revisi" rows="3" />
                    <x-ui.file-upload label="Lembar Revisi" name="revision_form" accept=".pdf" required />
                    <x-ui.file-upload label="Dokumen Revisi" name="revision_content" accept=".pdf" required />

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="mb-3 text-sm font-bold text-slate-900">Lampiran</p>
                        <x-documents.attachment-list />
                    </div>

                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">
                        <flux:icon name="arrow-path" class="size-4" />
                        Revisi
                    </button>
                </form>
            </div>
        @endif
    </x-slot:actions>
</x-documents.detail-page>
