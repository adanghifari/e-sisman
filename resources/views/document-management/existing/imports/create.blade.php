@php
    $isMasterImport = ($documentState ?? \App\Models\ImportedExistingDocument::STATE_OBSOLETE) === \App\Models\ImportedExistingDocument::STATE_MASTER;
@endphp

<x-layouts::app :title="$isMasterImport ? __('Import Dokumen Master') : __('Tambah Arsip Dokumen Existing')">
    <div class="space-y-6">
        <x-ui.page-header
            :title="$isMasterImport ? 'Import Dokumen Master' : 'Tambah Arsip Dokumen Existing'"
            :description="$isMasterImport ? 'Upload manual dokumen master existing sebelum go-live tanpa masuk approval awal.' : 'Upload manual dokumen obsolete tanpa mengubah lifecycle dokumen master.'"
        />

        <form method="POST" action="{{ route('documents.existing.imports.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="document_state" value="{{ $documentState ?? \App\Models\ImportedExistingDocument::STATE_OBSOLETE }}">

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Mohon periksa kembali data arsip. Ada isian yang belum sesuai.
                </div>
            @endif

            <x-ui.panel title="Identitas Dokumen" description="Mulai dari nama dokumen, lalu pilih ketentuan arsip yang sesuai.">
                @php
                    $selectedRuleType = old(
                        'obsolete_rule_type',
                        $isMasterImport ? \App\Models\ImportedExistingDocument::CURRENT_RULE : null,
                    );
                @endphp

                <div class="space-y-5" data-imported-existing-rule-form>
                    <div>
                        <x-ui.input label="Nama Dokumen" name="nama_dokumen" :value="old('nama_dokumen')" required />
                        @error('nama_dokumen')
                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Ketentuan</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="relative cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition has-[:checked]:border-sky-400 has-[:checked]:bg-sky-50 has-[:checked]:ring-2 has-[:checked]:ring-sky-100">
                                <input
                                    type="radio"
                                    name="obsolete_rule_type"
                                    value="{{ \App\Models\ImportedExistingDocument::CURRENT_RULE }}"
                                    class="sr-only"
                                    data-imported-existing-rule-option
                                    @checked($selectedRuleType === \App\Models\ImportedExistingDocument::CURRENT_RULE)
                                >
                                <span class="flex items-start gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                                        <flux:icon name="check-badge" class="size-5" />
                                    </span>
                                    <span>
                                        <span class="block font-semibold text-slate-900">Sesuai Ketentuan Saat Ini</span>
                                        <span class="mt-1 block text-sm leading-5 text-slate-500">Gunakan pemetaan master data modern seperti dok level, jenis dokumen, proses, dan fungsi.</span>
                                    </span>
                                </span>
                            </label>

                            @unless ($isMasterImport)
                                <label class="relative cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition has-[:checked]:border-sky-400 has-[:checked]:bg-sky-50 has-[:checked]:ring-2 has-[:checked]:ring-sky-100">
                                    <input
                                        type="radio"
                                        name="obsolete_rule_type"
                                        value="{{ \App\Models\ImportedExistingDocument::LEGACY_RULE }}"
                                        class="sr-only"
                                        data-imported-existing-rule-option
                                        @checked($selectedRuleType === \App\Models\ImportedExistingDocument::LEGACY_RULE)
                                    >
                                    <span class="flex items-start gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700">
                                            <flux:icon name="archive-box" class="size-5" />
                                        </span>
                                        <span>
                                            <span class="block font-semibold text-slate-900">Mengikuti Ketentuan Dokumen Lama</span>
                                            <span class="mt-1 block text-sm leading-5 text-slate-500">Simpan identitas historis dokumen sebagaimana tertulis pada arsip lama.</span>
                                        </span>
                                    </span>
                                </label>
                            @endunless
                        </div>
                        @error('obsolete_rule_type')
                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid gap-4 md:grid-cols-2" data-rule-dependent-fields>
                        <x-ui.input label="Nomor Dokumen" name="nomor_dokumen" :value="old('nomor_dokumen')" />
                        <x-ui.input label="Nomor Revisi" name="nomor_revisi" :value="old('nomor_revisi')" placeholder="Contoh: 00, 00.01, Rev A, R02" />
                        <x-ui.date-input label="Tanggal Terbit" name="tanggal_terbit" :value="old('tanggal_terbit')" />
                        @unless ($isMasterImport)
                            <x-ui.date-input label="Tanggal Obsolete" name="tanggal_obsolete" :value="old('tanggal_obsolete')" />
                        @endunless
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-current-rule-fields>
                        <div class="mb-4 flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-700 ring-1 ring-sky-100">
                                <flux:icon name="squares-2x2" class="size-5" />
                            </span>
                            <div>
                                <h3 class="font-semibold text-slate-900">Pemetaan Ketentuan Saat Ini</h3>
                                <p class="mt-1 text-sm leading-5 text-slate-500">{{ $isMasterImport ? 'Field ini wajib untuk imported master agar tampil seragam di Dokumen Master.' : 'Isi jika dokumen obsolete ini masih dapat dipetakan ke struktur dokumen modern.' }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.select label="Dok Level" name="m_document_level_id" :value="old('m_document_level_id')" :options="$documentLevelOptions" />
                            @error('m_document_level_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Jenis Dokumen" name="m_document_types_id" :value="old('m_document_types_id')" :options="$documentTypeOptions" />
                            @error('m_document_types_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Proses Bisnis" name="m_proses_bisnis_id" :value="old('m_proses_bisnis_id')" :options="$processOptions" />
                            @error('m_proses_bisnis_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Proses Fungsi" name="m_proses_fungsi_id" :value="old('m_proses_fungsi_id')" :options="$functionOptions" />
                            @error('m_proses_fungsi_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800" data-legacy-rule-note>
                        <p class="font-semibold">Metadata historis dokumen lama belum dipaksa ke struktur master data saat ini.</p>
                        <p class="mt-1">Untuk tahap ini, simpan nomor dokumen, revisi, tanggal, file, catatan, dan relasi dokumen. Field legacy tambahan akan ditentukan setelah audit format arsip aktual.</p>
                    </div>

                    <div data-rule-dependent-fields>
                        <x-ui.textarea label="Catatan Dokumen" name="catatan" :value="old('catatan')" :placeholder="$isMasterImport ? 'Catatan bebas terkait imported master ini.' : 'Catatan bebas terkait arsip obsolete ini.'" />
                    </div>
                </div>
            </x-ui.panel>

            <x-ui.panel title="File Dokumen" :description="$isMasterImport ? 'Upload file utama dokumen master existing dan lampiran pendukung jika ada.' : 'Upload file utama dokumen obsolete dan lampiran pendukung jika ada.'" data-rule-dependent-section>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.file-upload label="File Dokumen" :name="$isMasterImport ? 'existing_document' : 'obsolete_document'" accept=".pdf,.doc,.docx,.xls,.xlsx" required />
                    <x-ui.file-upload label="Lampiran" name="attachments[]" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple />
                </div>
                @error($isMasterImport ? 'existing_document' : 'obsolete_document')
                    <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                @enderror
                @error('attachments')
                    <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                @enderror
            </x-ui.panel>

            <x-ui.panel
                title="Dokumen Terkait"
                :description="$isMasterImport ? 'Tambahkan relasi jika dokumen master existing ini berhubungan dengan arsip obsolete legacy lain atau dokumen master V2.' : 'Tambahkan relasi jika arsip ini berhubungan dengan arsip obsolete legacy lain atau dokumen master existing.'"
                :padded="false"
                data-rule-dependent-section
            >
                @php
                    $oldRelations = collect(old('relations', []))->values();
                @endphp

                <div class="space-y-4 px-5 py-5" data-imported-existing-relations>
                    @error('relations.0.related_imported_existing_document_id')
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</div>
                    @enderror

                    <div class="space-y-4" data-imported-existing-relation-list>
                        @foreach ($oldRelations as $index => $relation)
                            @php
                                $targetType = filled($relation['related_document_id'] ?? null) ? 'existing' : 'imported';
                            @endphp

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-imported-existing-relation-row>
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Relasi Dokumen</p>
                                        <p class="mt-1 text-xs font-medium text-slate-500">Pilih tepat satu target relasi.</p>
                                    </div>
                                    <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-imported-existing-relation-remove aria-label="Hapus relasi">
                                        <flux:icon name="x-mark" class="size-4" />
                                    </button>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <x-ui.select
                                        label="Jenis Relasi"
                                        name="relations[{{ $index }}][relation_type]"
                                        :value="$relation['relation_type'] ?? \App\Models\ImportedExistingDocumentRelation::SUPERSEDED_BY"
                                        :options="$relationTypeOptions"
                                    />
                                    <x-ui.select
                                        label="Target Relasi"
                                        name="relations[{{ $index }}][target_type]"
                                        :value="$targetType"
                                        :options="['imported' => 'Arsip Obsolete Legacy', 'existing' => 'Dokumen Existing / Master']"
                                        data-imported-existing-target-type
                                    />
                                    <div data-imported-existing-imported-target>
                                        <x-ui.select
                                            label="Arsip Obsolete Legacy"
                                            name="relations[{{ $index }}][related_imported_existing_document_id]"
                                            :value="$relation['related_imported_existing_document_id'] ?? null"
                                            :options="['' => 'Pilih arsip obsolete legacy'] + $importedDocumentOptions->mapWithKeys(fn ($document) => [$document->id => trim(($document->nomor_dokumen ? $document->nomor_dokumen.' - ' : '').$document->nama_dokumen)])->all()"
                                        />
                                    </div>
                                    <div data-imported-existing-existing-target>
                                        <x-ui.select
                                            label="Dokumen Existing / Master"
                                            name="relations[{{ $index }}][related_document_id]"
                                            :value="$relation['related_document_id'] ?? null"
                                            :options="['' => 'Pilih dokumen existing'] + $existingDocumentOptions->mapWithKeys(fn ($document) => [$document->id => trim(($document->nomor_dokumen ? $document->nomor_dokumen.' - ' : '').$document->nama_dokumen)])->all()"
                                        />
                                    </div>
                                    <div class="lg:col-span-2">
                                        <x-ui.textarea
                                            label="Keterangan Relasi"
                                            name="relations[{{ $index }}][keterangan]"
                                            :value="$relation['keterangan'] ?? null"
                                            rows="3"
                                            placeholder="Keterangan khusus untuk hubungan antar dokumen."
                                        />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-imported-existing-relation-add>
                        <flux:icon name="plus" class="size-4" />
                        Tambah Relasi
                    </button>
                </div>

                <template data-imported-existing-relation-template>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-imported-existing-relation-row>
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Relasi Dokumen</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">Pilih tepat satu target relasi.</p>
                            </div>
                            <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-imported-existing-relation-remove aria-label="Hapus relasi">
                                <flux:icon name="x-mark" class="size-4" />
                            </button>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Relasi</span>
                                <select name="relations[__INDEX__][relation_type]" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                    @foreach ($relationTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target Relasi</span>
                                <select name="relations[__INDEX__][target_type]" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100" data-imported-existing-target-type>
                                    <option value="imported">Arsip Obsolete Legacy</option>
                                    <option value="existing">Dokumen Existing / Master</option>
                                </select>
                            </label>
                            <div data-imported-existing-imported-target>
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Arsip Obsolete Legacy</span>
                                    <select name="relations[__INDEX__][related_imported_existing_document_id]" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option value="">Pilih arsip obsolete legacy</option>
                                        @foreach ($importedDocumentOptions as $optionDocument)
                                            <option value="{{ $optionDocument->id }}">{{ trim(($optionDocument->nomor_dokumen ? $optionDocument->nomor_dokumen.' - ' : '').$optionDocument->nama_dokumen) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div data-imported-existing-existing-target>
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen Existing / Master</span>
                                    <select name="relations[__INDEX__][related_document_id]" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option value="">Pilih dokumen existing</option>
                                        @foreach ($existingDocumentOptions as $optionDocument)
                                            <option value="{{ $optionDocument->id }}">{{ trim(($optionDocument->nomor_dokumen ? $optionDocument->nomor_dokumen.' - ' : '').$optionDocument->nama_dokumen) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan Relasi</span>
                                    <textarea name="relations[__INDEX__][keterangan]" rows="3" placeholder="Keterangan khusus untuk hubungan antar dokumen." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </x-ui.panel>

            <div class="flex justify-end gap-3">
                <a href="{{ route('documents.existing.imports.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Batal
                </a>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    {{ $isMasterImport ? 'Simpan Dokumen Master' : 'Simpan Arsip' }}
                </button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const ruleForm = document.querySelector('[data-imported-existing-rule-form]');

            if (ruleForm) {
                const syncRuleFields = () => {
                    const selectedRule = ruleForm.querySelector('[data-imported-existing-rule-option]:checked')?.value;
                    const currentRuleFields = ruleForm.querySelector('[data-current-rule-fields]');
                    const legacyRuleNote = ruleForm.querySelector('[data-legacy-rule-note]');
                    const dependentFields = document.querySelectorAll('[data-rule-dependent-fields]');
                    const dependentSections = document.querySelectorAll('[data-rule-dependent-section]');
                    const hasSelectedRule = Boolean(selectedRule);
                    const isCurrentRule = selectedRule === '{{ \App\Models\ImportedExistingDocument::CURRENT_RULE }}';

                    dependentFields.forEach((section) => {
                        section.classList.toggle('hidden', !hasSelectedRule);
                    });
                    dependentSections.forEach((section) => {
                        section.classList.toggle('hidden', !hasSelectedRule);
                    });

                    currentRuleFields?.classList.toggle('hidden', !hasSelectedRule || !isCurrentRule);
                    legacyRuleNote?.classList.toggle('hidden', !hasSelectedRule || isCurrentRule);

                    currentRuleFields?.querySelectorAll('select, input, textarea').forEach((field) => {
                        field.disabled = !hasSelectedRule || !isCurrentRule;
                    });
                };

                document.addEventListener('change', (event) => {
                    if (event.target.closest('[data-imported-existing-rule-option]')) {
                        syncRuleFields();
                    }
                });

                syncRuleFields();
            }

            const root = document.querySelector('[data-imported-existing-relations]');

            if (!root) {
                return;
            }

            const list = root.querySelector('[data-imported-existing-relation-list]');
            const template = document.querySelector('[data-imported-existing-relation-template]');
            let nextIndex = list?.querySelectorAll('[data-imported-existing-relation-row]').length || 0;

            const syncTargetVisibility = (row) => {
                const type = row.querySelector('[data-imported-existing-target-type]')?.value || 'imported';
                const importedTarget = row.querySelector('[data-imported-existing-imported-target]');
                const existingTarget = row.querySelector('[data-imported-existing-existing-target]');
                const importedSelect = importedTarget?.querySelector('select');
                const existingSelect = existingTarget?.querySelector('select');

                importedTarget?.classList.toggle('hidden', type !== 'imported');
                existingTarget?.classList.toggle('hidden', type !== 'existing');

                if (importedSelect) {
                    importedSelect.disabled = type !== 'imported';
                }

                if (existingSelect) {
                    existingSelect.disabled = type !== 'existing';
                }
            };

            const syncAllRows = () => {
                list?.querySelectorAll('[data-imported-existing-relation-row]').forEach(syncTargetVisibility);
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-imported-existing-relation-add]')) {
                    const content = template?.innerHTML.replaceAll('__INDEX__', String(nextIndex));

                    if (!content || !list) {
                        return;
                    }

                    list.insertAdjacentHTML('beforeend', content);
                    nextIndex += 1;
                    syncAllRows();
                    return;
                }

                const removeButton = event.target.closest('[data-imported-existing-relation-remove]');

                if (removeButton) {
                    removeButton.closest('[data-imported-existing-relation-row]')?.remove();
                }
            });

            document.addEventListener('change', (event) => {
                const targetType = event.target.closest('[data-imported-existing-target-type]');

                if (!targetType) {
                    return;
                }

                const row = targetType.closest('[data-imported-existing-relation-row]');

                if (row) {
                    syncTargetVisibility(row);
                }
            });

            syncAllRows();
        })();
    </script>
</x-layouts::app>

