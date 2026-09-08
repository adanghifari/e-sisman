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
    :download-printout-url="$downloadPrintoutUrl"
    :show-file-open-button="false"
    :show-attachments-section="false"
    :document-history="collect()"
    :related-obsolete-documents="$relatedObsoleteDocuments"
    :show-owner-section="false"
    :show-approval-history="false"
    :document-history-title="'Catatan Import'"
    :document-history-notice="$importNote"
    document-name-badge="Imported"
>
    <x-slot:actions>
        @if (($canEdit ?? false) || $canRequestRevision || $canRequestObsolete)
            <div class="border-t border-dashed border-slate-200 px-6 py-5">
                <div class="grid gap-3">
                    @if ($canEdit ?? false)
                        <a href="{{ route('documents.master.imports.edit', $document) }}" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" wire:navigate>
                            <flux:icon name="pencil-square" class="size-4" />
                            Edit Dokumen
                        </a>
                    @endif
                    @if ($canRequestRevision)
                        @if ($hasActiveRevisionRequest ?? false)
                            <button type="button" class="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-100 px-4 text-sm font-semibold text-slate-400 shadow-sm" disabled>
                                <flux:icon name="clock" class="size-4" />
                                Revisi dalam Pengajuan
                            </button>
                        @else
                            <a
                                href="{{ route('documents.create.level', ['level-4', 'imported_source' => $document->id]) }}"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700"
                                wire:navigate
                            >
                                <flux:icon name="arrow-path" class="size-4" />
                                Ajukan Revisi
                            </a>
                        @endif
                    @endif
                    @if ($canRequestObsolete)
                        <button type="button" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 shadow-sm transition hover:border-red-300 hover:bg-red-50" data-imported-obsolete-modal-open>
                            <flux:icon name="archive-box-x-mark" class="size-4" />
                            Obsolete
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </x-slot:actions>

    <x-slot:modals>

        @if ($canRequestObsolete)
            <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-imported-obsolete-modal>
                <form method="POST" action="{{ route('documents.master.imported.obsolete', $document) }}" class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
                    @csrf
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Pengajuan Obsolete</h2>
                            <p class="mt-1 text-sm text-slate-500">Isi alasan obsolete untuk memindahkan imported master ini ke arsip obsolete.</p>
                        </div>
                        <button type="button" class="text-slate-400 transition hover:text-slate-700" data-imported-obsolete-modal-close>
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>
                    <div class="px-6 py-5">
                        <x-ui.textarea
                            label="Catatan / Alasan Obsolete"
                            name="catatan_obsolete"
                            rows="5"
                            placeholder="Tulis alasan obsolete..."
                            required
                        />
                        @error('catatan_obsolete')
                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <x-ui.action-button type="button" variant="secondary" data-imported-obsolete-modal-close>
                            Batal
                        </x-ui.action-button>
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700">
                            Obsolete
                        </button>
                    </div>
                </form>
            </div>

            <script>
                (() => {
                    const modal = document.querySelector('[data-imported-obsolete-modal]');

                    document.addEventListener('click', (event) => {
                        if (event.target.closest('[data-imported-obsolete-modal-open]')) {
                            modal?.classList.remove('hidden');
                            modal?.classList.add('flex');
                        }

                        if (event.target.closest('[data-imported-obsolete-modal-close]') || event.target === modal) {
                            modal?.classList.add('hidden');
                            modal?.classList.remove('flex');
                        }
                    });
                })();
            </script>
        @endif
    </x-slot:modals>
</x-documents.detail-page>
