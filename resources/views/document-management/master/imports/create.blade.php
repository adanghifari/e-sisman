<x-layouts::app :title="__('Import Dokumen Master')">
    @php
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
        ];
        $documentTitle = \Illuminate\Support\Str::after($levelConfig['name'], ': ');
        $levelDisplayValue = $documentLevelRecord
            ? $documentLevelRecord->nama_level.' : '.\Illuminate\Support\Str::after($documentLevelRecord->nama_dokumen, ': ')
            : $levelConfig['badge'].' : '.$documentTitle;
        $selectedBusinessProcessId = old('m_proses_bisnis_id');
        $selectedBusinessFunctionId = old('m_proses_fungsi_id');
        $selectedDepartmentIds = $selectedDepartmentIds ?? [];
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.master') }}" class="transition hover:text-sky-700" wire:navigate>Dokumen Master</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.master.imports.create') }}" class="transition hover:text-sky-700" wire:navigate>Import Dokumen Master</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $levelConfig['badge'] }}</span>
        </nav>

        <div>
            <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                Import Dokumen Level {{ $levelNumbers[$level] }} : {{ $documentTitle }}
            </h1>
        </div>

        <form
            method="POST"
            action="{{ route('documents.master.imports.store.level', $level) }}"
            enctype="multipart/form-data"
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
        >
            @csrf

            <div class="space-y-6">
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        <p>Import dokumen belum berhasil. Cek isian berikut:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Informasi Dokumen --}}
                <x-documents.form-section title="Informasi Dokumen">
                    <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                            <input
                                type="text"
                                name="nama_dokumen"
                                value="{{ old('nama_dokumen') }}"
                                placeholder="Masukkan nama dokumen"
                                required
                                @class([
                                    'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                    'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                    'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                ])
                            >
                        </label>

                        <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                            <select
                                name="m_proses_bisnis_id"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                                @foreach ($processOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $selectedBusinessProcessId === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('m_proses_bisnis_id')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                            <select
                                name="m_proses_fungsi_id"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                                @foreach ($functionOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $selectedBusinessFunctionId === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('m_proses_fungsi_id')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <x-ui.multi-select
                            label="Department Terkait"
                            name="department_ids"
                            :options="$departmentOptions"
                            :selected="$selectedDepartmentIds"
                            selected-placeholder="Tambah Department"
                            required
                        />

                        @if ($level === 'level-3')
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Pilih Dokumen Level II: Prosedur</span>
                                <select
                                    name="reference"
                                    required
                                    class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="">-Pilih-</option>
                                    @if ($importedProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Existing / Imported">
                                            @foreach ($importedProcedures as $proc)
                                                <option
                                                    value="imported-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) old('reference') === "imported-{$proc->id}")
                                                >
                                                    {{ $proc->nomor_dokumen ?: '-' }} - {{ $proc->nama_dokumen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if ($workflowProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Master V2">
                                            @foreach ($workflowProcedures as $proc)
                                                <option
                                                    value="existing-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) old('reference') === "existing-{$proc->id}")
                                                >
                                                    {{ $proc->nomor_dokumen ?: '-' }} - {{ $proc->nama_dokumen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                                @error('reference')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        @endif
                    </div>
                </x-documents.form-section>

                {{-- Isi Dokumen --}}
                <x-documents.form-section title="Isi Dokumen" icon="cloud-arrow-up">
                    <div class="space-y-6 px-6 py-6">
                        <x-ui.file-upload
                            label="File Dokumen Utama"
                            name="existing_document"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            hint="Format PDF, Word, atau Excel. Maks 10 MB."
                            :max-files="1"
                            :max-file-size-kb="10240"
                            required
                        />
                        @error('existing_document')
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror

                        <x-ui.file-upload
                            label="Lampiran"
                            name="attachments[]"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            hint="Opsional. Bisa lebih dari satu file."
                            multiple
                            :max-files="10"
                            :max-file-size-kb="10240"
                        />
                        @error('attachments')
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </x-documents.form-section>
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6 xl:sticky xl:top-8">
                <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <input
                                type="text"
                                name="nomor_dokumen"
                                value="{{ old('nomor_dokumen') }}"
                                placeholder="Contoh: SM-001"
                                required
                                @class([
                                    'h-14 w-full rounded-lg px-4 text-base font-semibold outline-none transition',
                                    'border border-red-300 bg-white text-slate-700 focus:border-red-400 focus:ring-2 focus:ring-red-100' => $errors->has('nomor_dokumen'),
                                    'border border-slate-300 bg-white text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' => ! $errors->has('nomor_dokumen'),
                                ])
                            >
                            @error('nomor_dokumen')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Revisi</span>
                            <input
                                type="text"
                                name="nomor_revisi"
                                value="{{ old('nomor_revisi') }}"
                                placeholder="__.__"
                                inputmode="numeric"
                                pattern="\d{2}\.\d{2}"
                                title="Gunakan format 00.00"
                                autocomplete="off"
                                required
                                data-import-master-revision-mask
                                @class([
                                    'h-14 w-full rounded-lg bg-white px-4 font-mono text-base font-semibold tracking-normal outline-none transition placeholder:text-slate-400',
                                    'border border-red-300 text-red-700 focus:border-red-400 focus:ring-2 focus:ring-red-100' => $errors->has('nomor_revisi'),
                                    'border border-slate-300 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' => ! $errors->has('nomor_revisi'),
                                ])
                            >
                            @error('nomor_revisi')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <x-ui.date-input
                            label="Tanggal Terbit"
                            name="tanggal_terbit"
                            :value="old('tanggal_terbit')"
                        />
                        @error('tanggal_terbit')
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                            <textarea
                                name="catatan"
                                rows="4"
                                placeholder="Catatan bebas terkait imported master ini."
                                class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >{{ old('catatan') }}</textarea>
                        </div>

                        <div class="space-y-3 text-sm font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <flux:icon name="document-check" class="size-5 text-slate-700" />
                                <span>Level</span>
                                <span class="ml-auto rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-700">{{ $levelConfig['badge'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:icon name="arrow-path" class="size-5 text-slate-700" />
                                <span>Status</span>
                                <span class="ml-auto rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Master</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <div class="flex gap-3">
                            <a
                                href="{{ route('documents.master.imports.create') }}"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50"
                                wire:navigate
                            >
                                Batal
                            </a>
                            <button
                                type="submit"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-sky-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-sky-700"
                            >
                                Simpan Master
                            </button>
                        </div>
                    </div>
                </section>
            </aside>
        </form>
    </div>

    <script>
        (() => {
            // Sync filter procedure reference by process & function
            const syncProcedureReferenceOptions = (form) => {
                const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                const referenceSelect = form.querySelector('select[name="reference"]');

                if (!processSelect || !functionSelect || !referenceSelect) {
                    return;
                }

                const processId = processSelect.value;
                const functionId = functionSelect.value;
                let selectedReferenceStillValid = referenceSelect.value === '';

                // Loop options & optgroups
                Array.from(referenceSelect.querySelectorAll('option')).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const matches = processId !== ''
                        && option.dataset.businessProcessId === processId
                        && (functionId === '' || option.dataset.businessFunctionId === functionId);

                    option.hidden = !matches;
                    option.disabled = !matches;

                    if (option.selected && matches) {
                        selectedReferenceStillValid = true;
                    }
                });

                // Toggle visibility of optgroups
                Array.from(referenceSelect.querySelectorAll('optgroup')).forEach((group) => {
                    const visibleOptions = Array.from(group.querySelectorAll('option')).filter(opt => !opt.hidden);
                    group.hidden = visibleOptions.length === 0;
                });

                referenceSelect.disabled = processId === '';

                if (!selectedReferenceStillValid) {
                    referenceSelect.value = '';
                }
            };

            document.querySelectorAll('form').forEach((form) => {
                syncProcedureReferenceOptions(form);
            });

            document.addEventListener('change', (event) => {
                if (!event.target.closest('select[name="m_proses_bisnis_id"], select[name="m_proses_fungsi_id"]')) {
                    return;
                }
                const form = event.target.closest('form');
                if (form) {
                    syncProcedureReferenceOptions(form);
                }
            });

            const revisionPattern = /^\d{2}\.\d{2}$/;
            const revisionPlaceholder = '__.__';

            const revisionDigits = (value) => value.replace(/\D/g, '').slice(0, 4);
            const revisionPositionForDigitCount = (digitCount) => {
                if (digitCount <= 0) {
                    return 0;
                }

                if (digitCount <= 2) {
                    return digitCount;
                }

                return digitCount + 1;
            };

            const revisionDigitCountBeforePosition = (value, position) => {
                return revisionDigits(value.slice(0, position)).length;
            };

            const formatRevision = (value, padded = true) => {
                const digits = revisionDigits(value);
                const chars = revisionPlaceholder.split('');
                let digitIndex = 0;

                for (let index = 0; index < chars.length && digitIndex < digits.length; index += 1) {
                    if (chars[index] === '_') {
                        chars[index] = digits[digitIndex];
                        digitIndex += 1;
                    }
                }

                if (padded) {
                    return chars.join('');
                }

                return digits.length > 2
                    ? `${digits.slice(0, 2)}.${digits.slice(2)}`
                    : digits;
            };

            const setRevisionValidity = (input) => {
                input.setCustomValidity(
                    revisionPattern.test(input.value)
                        ? ''
                        : 'Nomor revisi wajib menggunakan format 00.00.'
                );
            };

            const syncRevisionInput = (input, nextDigitPosition = null) => {
                const cursorPosition = input.selectionStart ?? input.value.length;
                const digitPosition = nextDigitPosition ?? revisionDigitCountBeforePosition(input.value, cursorPosition);

                input.value = formatRevision(input.value);
                setRevisionValidity(input);

                window.requestAnimationFrame(() => {
                    const nextCursorPosition = revisionPositionForDigitCount(digitPosition);
                    input.setSelectionRange(nextCursorPosition, nextCursorPosition);
                });
            };

            document.querySelectorAll('[data-import-master-revision-mask]').forEach((input) => {
                syncRevisionInput(input, revisionDigits(input.value).length);

                input.addEventListener('focus', () => {
                    syncRevisionInput(input, revisionDigits(input.value).length);
                });

                input.addEventListener('click', () => {
                    syncRevisionInput(input);
                });

                input.addEventListener('keydown', (event) => {
                    if (!['Backspace', 'Delete'].includes(event.key)) {
                        return;
                    }

                    const cursorStart = input.selectionStart ?? 0;
                    const cursorEnd = input.selectionEnd ?? cursorStart;
                    const digits = revisionDigits(input.value);
                    const digitStart = revisionDigitCountBeforePosition(input.value, cursorStart);
                    const digitEnd = revisionDigitCountBeforePosition(input.value, cursorEnd);

                    let nextDigits = digits;
                    let nextDigitPosition = digitStart;

                    if (cursorStart !== cursorEnd && digitStart !== digitEnd) {
                        nextDigits = digits.slice(0, digitStart) + digits.slice(digitEnd);
                    } else if (event.key === 'Backspace' && digitStart > 0) {
                        nextDigits = digits.slice(0, digitStart - 1) + digits.slice(digitStart);
                        nextDigitPosition = digitStart - 1;
                    } else if (event.key === 'Delete' && digitStart < digits.length) {
                        nextDigits = digits.slice(0, digitStart) + digits.slice(digitStart + 1);
                    } else {
                        return;
                    }

                    event.preventDefault();
                    input.value = nextDigits;
                    syncRevisionInput(input, nextDigitPosition);
                });

                input.addEventListener('input', () => {
                    syncRevisionInput(input);
                });

                input.addEventListener('blur', () => {
                    input.value = formatRevision(input.value);
                    setRevisionValidity(input);
                });
            });
        })();
    </script>
</x-layouts::app>
