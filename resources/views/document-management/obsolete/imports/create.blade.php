<x-layouts::app :title="__('Tambah Arsip Dokumen Obsolete Legacy')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Tambah Arsip Dokumen Obsolete Legacy"
            description="Upload manual dokumen obsolete tanpa mengubah lifecycle dokumen master."
        />

        <form method="POST" action="{{ route('documents.obsolete.imports.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <x-ui.panel title="Identitas Dokumen" description="Master data modern boleh dikosongkan jika dokumen lama tidak cocok dengan struktur saat ini.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.select label="Jenis Ketentuan" name="obsolete_rule_type" :value="old('obsolete_rule_type', \App\Models\ImportedObsoleteDocument::LEGACY_RULE)" :options="collect($ruleOptions)->except('')->all()" />
                    <x-ui.input label="Nama Dokumen" name="nama_dokumen" :value="old('nama_dokumen')" required />
                    <x-ui.input label="Nomor Dokumen" name="nomor_dokumen" :value="old('nomor_dokumen')" />
                    <x-ui.input label="Nomor Revisi" name="nomor_revisi" :value="old('nomor_revisi')" placeholder="Contoh: 00, 00.01, Rev A, R02" />
                    <x-ui.date-input label="Tanggal Terbit" name="tanggal_terbit" :value="old('tanggal_terbit')" />
                    <x-ui.date-input label="Tanggal Obsolete" name="tanggal_obsolete" :value="old('tanggal_obsolete')" />
                    <x-ui.select label="Dok Level" name="m_document_level_id" :value="old('m_document_level_id')" :options="$documentLevelOptions" />
                    <x-ui.select label="Jenis Dokumen" name="m_document_types_id" :value="old('m_document_types_id')" :options="$documentTypeOptions" />
                    <x-ui.select label="Proses Bisnis" name="m_proses_bisnis_id" :value="old('m_proses_bisnis_id')" :options="$processOptions" />
                    <x-ui.select label="Proses Fungsi" name="m_proses_fungsi_id" :value="old('m_proses_fungsi_id')" :options="$functionOptions" />
                </div>

                <div class="mt-4">
                    <x-ui.textarea label="Catatan Dokumen" name="catatan" :value="old('catatan')" placeholder="Catatan bebas terkait arsip obsolete ini." />
                </div>
            </x-ui.panel>

            <x-ui.panel title="File Dokumen" description="Upload file utama dokumen obsolete dan lampiran pendukung jika ada.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.file-upload label="File Dokumen" name="obsolete_document" accept=".pdf,.doc,.docx,.xls,.xlsx" required />
                    <x-ui.file-upload label="Lampiran" name="attachments[]" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple />
                </div>
            </x-ui.panel>

            <div class="flex justify-end gap-3">
                <a href="{{ route('documents.obsolete.imports.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    Batal
                </a>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Simpan Arsip
                </button>
            </div>
        </form>
    </div>
</x-layouts::app>
