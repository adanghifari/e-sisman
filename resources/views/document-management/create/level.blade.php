<x-layouts::app :title="__('Tambah Dokumen')">
    @php
        $levelKey = request()->route('level');
        $level = config("document-levels.{$levelKey}");
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
        ];
        $documentPrefixes = [
            'level-1' => 'SM',
            'level-2' => 'PS',
            'level-3' => 'IK',
        ];
        $parentLabels = [
            'level-1' => 'Pilih Referensi Dokumen',
            'level-2' => 'Pilih Dokumen Level I : Manual',
            'level-3' => 'Pilih Dokumen Level II : Prosedur',
        ];
        $ownerLabel = $levelKey === 'level-1' ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
        $documentTitle = \Illuminate\Support\Str::after($level['name'], ': ');
        $assignableUsers = \App\Models\User::query()
            ->with('department')
            ->when(auth()->check(), fn ($query) => $query->whereKeyNot(auth()->id()))
            ->orderBy('name')
            ->get();
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.create') }}" class="transition hover:text-sky-700" wire:navigate>Tambah Dokumen</a>
            <flux:icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $level['badge'] }}</span>
        </nav>

        <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
            {{ $levelKey === 'level-1' ? 'Import' : 'Tambah' }} Dokumen Level {{ $levelNumbers[$levelKey] }} : {{ $documentTitle }}
        </h1>

        @if ($levelKey === 'level-1')
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Informasi Dokumen</h2>
                        </div>

                        <div class="px-6 py-6">
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                <input
                                    type="text"
                                    placeholder="Masukan nama dokumen"
                                    class="h-14 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                            </label>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Upload Dokumen</h2>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <label class="flex min-h-56 cursor-pointer items-center gap-8 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-7 transition hover:border-sky-300 hover:bg-sky-50/40">
                                <input type="file" class="sr-only">
                                <span class="grid size-40 shrink-0 place-items-center rounded-full bg-sky-100 text-sky-500">
                                    <flux:icon name="cloud-arrow-up" class="size-20" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xl font-bold text-slate-950">Drag & Drop atau Pilih file</span>
                                    <span class="mt-3 block max-w-md text-base leading-7 text-slate-500">
                                        Letakkan file di sini atau klik untuk upload file
                                    </span>
                                </span>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                                <textarea
                                    rows="5"
                                    placeholder="Tambahkan catatan dokumen"
                                    class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                ></textarea>
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:sticky xl:top-8">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4">
                                <input type="text" value="{{ $documentPrefixes[$levelKey] }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                <span class="text-lg font-semibold text-slate-500">-</span>
                                <input type="text" class="h-14 w-full rounded-lg border border-slate-300 bg-white px-3 text-center text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                            </div>
                        </div>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input
                                type="text"
                                class="h-14 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Tanggal Terbit</span>
                            <div class="relative">
                                <input
                                    type="text"
                                    placeholder="DD/MM/YYYY"
                                    class="h-14 w-full rounded-lg border border-slate-300 bg-white px-4 pr-12 text-base font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                <flux:icon name="calendar" class="pointer-events-none absolute right-4 top-1/2 size-6 -translate-y-1/2 text-slate-500" />
                            </div>
                        </label>
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <button type="button" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                            Import Dokumen
                        </button>
                    </div>
                </aside>
            </div>
        @else
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_520px]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Informasi Dokumen</h2>
                        </div>

                        <div class="grid gap-5 px-6 py-6">
                            @if ($levelKey === 'level-2')
                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                    <input
                                        type="text"
                                        placeholder="Masukan nama dokumen"
                                        class="h-14 w-full rounded-lg border border-red-300 bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-red-400 focus:ring-2 focus:ring-red-100"
                                    >
                                    <span class="mt-2 block text-sm font-semibold text-red-500">Nama Dokumen wajib diisi</span>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                                    <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option>Sistem Manajemen & Risiko</option>
                                    </select>
                                </label>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                        <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option>Proses Inti/ Utama</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Department Terkait</span>
                                        <div class="flex min-h-12 items-center rounded-lg border border-sky-300 bg-white px-3 text-base font-medium text-slate-500 shadow-sm ring-2 ring-sky-100">
                                            <span class="inline-flex max-w-[78%] items-center gap-2 truncate rounded-md bg-blue-50 px-3 py-1.5 text-sm font-semibold text-slate-600 ring-1 ring-blue-200">
                                                <span class="truncate">Stevedoring O...</span>
                                                <span class="grid size-5 shrink-0 place-items-center rounded-full bg-blue-500 text-xs font-bold text-white">x</span>
                                            </span>
                                            <span class="ml-auto text-xl leading-none text-slate-400">x</span>
                                            <flux:icon name="chevron-down" class="ml-2 size-5 shrink-0 text-slate-500" />
                                        </div>
                                    </label>
                                </div>
                            @else
                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                    <input
                                        type="text"
                                        placeholder="Masukan nama dokumen"
                                        class="h-14 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                    >
                                </label>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                                        <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option>-Pilih-</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">{{ $parentLabels[$levelKey] }}</span>
                                        <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option>-Pilih-</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                        <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option>-Pilih-</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Department Terkait</span>
                                        <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option>-Pilih-</option>
                                        </select>
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" data-official-preparer>
                        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">{{ $ownerLabel }}</h2>

                            @if ($levelKey === 'level-3')
                                <button
                                    type="button"
                                    class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                    data-use-current-user
                                    data-user-id="{{ auth()->id() }}"
                                    data-user-name="{{ auth()->user()->name }}"
                                    data-user-email="{{ auth()->user()->email }}"
                                    data-user-title="{{ auth()->user()->jabatan }}"
                                    data-user-initials="{{ auth()->user()->initials() }}"
                                >
                                    Saya Mengajukan tanpa Perwakilan
                                </button>
                            @else
                                <button type="button" class="text-base font-semibold text-blue-500 transition hover:text-blue-600">
                                    {{ $ownerLabel }}
                                </button>
                            @endif
                        </div>

                        @if ($levelKey === 'level-3')
                            <div class="space-y-5 px-6 py-6">
                                <input type="hidden" name="official_preparer_id" value="" data-official-preparer-input>

                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pengisi Form</span>
                                    <div class="mt-2 flex items-center gap-2.5">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                            {{ auth()->user()->initials() }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ auth()->user()->name }}</span>
                                            <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">
                                                {{ auth()->user()->jabatan ?: auth()->user()->email }}
                                            </span>
                                        </span>
                                        <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">
                                            Tercatat di sistem
                                        </span>
                                    </div>
                                </div>

                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Pilih Penyusun Resmi</span>
                                    <select class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100" data-official-preparer-select>
                                        <option value="">-Pilih penyusun pemilik proses-</option>
                                        @foreach ($assignableUsers as $user)
                                            <option
                                                value="{{ $user->id }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-title="{{ $user->jabatan }}"
                                                data-initials="{{ $user->initials() }}"
                                            >
                                                {{ $user->name }}{{ $user->jabatan ? ' - '.$user->jabatan : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <div class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3" data-official-preparer-card>
                                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Penyusun Resmi</span>
                                    <div class="mt-2 flex items-center gap-2.5">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-200" data-official-preparer-initials></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-bold leading-tight text-slate-900" data-official-preparer-name></span>
                                            <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500" data-official-preparer-meta></span>
                                        </span>
                                        <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200" data-official-preparer-source></span>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500" data-official-preparer-empty>
                                    Penyusun resmi masih kosong. Pilih user pada daftar di atas, atau gunakan tombol
                                    <span class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-2 py-0.5 font-semibold text-sky-700">Saya Mengajukan tanpa Perwakilan</span>
                                    jika pengisi form juga menjadi penyusun resmi.
                                </div>
                            </div>
                        @else
                            <div class="min-h-36 px-6 py-6"></div>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Isi Dokumen</h2>
                        </div>

                        <div class="space-y-6 px-6 py-6">
                            <div class="rounded-lg border border-sky-100 bg-sky-50/40 px-4 py-4" data-document-upload>
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-sky-700 ring-1 ring-sky-100">
                                            <flux:icon name="cloud-arrow-up" class="size-5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-base font-bold text-slate-900">Template Dokumen yang Sudah Diisi</span>
                                            <span class="mt-1 block text-sm leading-6 text-slate-500">
                                                Upload file template dokumen final yang sudah dilengkapi.
                                            </span>
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-sky-200 bg-white px-4 text-sm font-semibold text-sky-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                        data-document-upload-trigger
                                        aria-expanded="false"
                                    >
                                        Upload Template
                                    </button>
                                </div>

                                <div class="mt-4 hidden" data-document-upload-panel>
                                    <x-ui.file-upload
                                        label="Upload Template Terisi"
                                        name="filled_template"
                                        accept=".pdf,.doc,.docx"
                                        hint="Format PDF, DOC, atau DOCX."
                                        :max-files="1"
                                        :max-file-size-kb="10240"
                                    />
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4" data-document-upload>
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <span class="min-w-0">
                                        <span class="block text-base font-bold text-slate-900">Daftar Dokumen</span>
                                        <span class="mt-2 inline-flex rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                                            Lampiran
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                        data-document-upload-trigger
                                        aria-expanded="false"
                                    >
                                        Tambah Dokumen
                                    </button>
                                </div>

                                <div class="mt-4 hidden" data-document-upload-panel>
                                    <x-ui.file-upload
                                        label="Upload Lampiran"
                                        name="attachments[]"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                        hint="Bisa lebih dari satu file. Format PDF, Office, JPG, JPEG, atau PNG."
                                        multiple
                                        :max-files="10"
                                        :max-file-size-kb="10240"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:sticky xl:top-8">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        @if ($levelKey === 'level-2')
                            <div>
                                <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                                    <input type="text" value="{{ $documentPrefixes[$levelKey] }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                    <span class="text-lg font-semibold text-slate-500">-</span>
                                    <input type="text" value="SMR" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                    <span class="text-lg font-semibold text-slate-500">-</span>
                                    <input type="text" value="2" class="h-14 w-full rounded-lg border border-slate-300 bg-white px-3 text-center text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                </div>
                            </div>
                        @else
                            <div>
                                <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                                <div class="flex flex-wrap items-center gap-3">
                                    <input type="text" value="{{ $documentPrefixes[$levelKey] }}" readonly class="h-14 w-20 rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                    <span class="text-lg font-semibold text-slate-500">-</span>
                                    <input type="text" value="XXX" readonly class="h-14 w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                    <span class="text-lg font-semibold text-slate-500">-</span>
                                    <input type="text" value="YY" readonly class="h-14 w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                    <span class="text-lg font-semibold text-slate-500">-</span>
                                    <input type="text" class="h-14 min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 text-center text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                </div>
                            </div>
                        @endif

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input
                                type="text"
                                value="00.00"
                                readonly
                                class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600"
                            >
                        </label>

                        <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <flux:icon name="arrow-path" class="size-6 text-slate-700" />
                                <span>Status</span>
                                <span class="ml-auto rounded-full bg-slate-200 px-3 py-1 text-sm font-bold text-slate-700">Draft</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar-days" class="size-6 text-slate-700" />
                                <span>Tanggal Pengajuan</span>
                                <span class="ml-auto text-slate-500">-</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <flux:icon name="calendar" class="size-6 text-slate-700" />
                                <span>Tanggal Terbit</span>
                                <span class="ml-auto text-slate-500">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 border-t border-dashed border-slate-200 px-6 py-5 sm:grid-cols-2">
                        <button type="button" class="inline-flex h-12 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50">
                            Simpan Draft
                        </button>
                        <button type="button" class="inline-flex h-12 items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                            Submit Dokumen
                        </button>
                    </div>
                </aside>
            </div>
        @endif
    </div>

    @once
        <script>
            (() => {
                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-document-upload-trigger]');

                    if (!button) {
                        return;
                    }

                    const root = button.closest('[data-document-upload]');
                    const panel = root?.querySelector('[data-document-upload-panel]');

                    if (!panel) {
                        return;
                    }

                    panel.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                    button.classList.add('hidden');
                });
            })();
        </script>
    @endonce

    @if ($levelKey === 'level-3')
        @once
            <script>
                (() => {
                    const syncOfficialPreparer = (root, user, sourceLabel) => {
                        const input = root.querySelector('[data-official-preparer-input]');
                        const card = root.querySelector('[data-official-preparer-card]');
                        const empty = root.querySelector('[data-official-preparer-empty]');
                        const initials = root.querySelector('[data-official-preparer-initials]');
                        const name = root.querySelector('[data-official-preparer-name]');
                        const meta = root.querySelector('[data-official-preparer-meta]');
                        const source = root.querySelector('[data-official-preparer-source]');

                        if (!input || !card || !empty || !initials || !name || !meta || !source) {
                            return;
                        }

                        input.value = user.id || '';
                        initials.textContent = user.initials || '-';
                        name.textContent = user.name || '-';
                        meta.textContent = user.title || user.email || '-';
                        source.textContent = sourceLabel;
                        card.classList.remove('hidden');
                        empty.classList.add('hidden');
                    };

                    document.addEventListener('click', (event) => {
                        const button = event.target.closest('[data-use-current-user]');

                        if (!button) {
                            return;
                        }

                        const root = button.closest('[data-official-preparer]');
                        const select = root?.querySelector('[data-official-preparer-select]');

                        if (select) {
                            select.value = '';
                        }

                        syncOfficialPreparer(root, {
                            id: button.dataset.userId,
                            name: button.dataset.userName,
                            email: button.dataset.userEmail,
                            title: button.dataset.userTitle,
                            initials: button.dataset.userInitials,
                        }, 'Tanpa perwakilan');
                    });

                    document.addEventListener('change', (event) => {
                        const select = event.target.closest('[data-official-preparer-select]');

                        if (!select || !select.value) {
                            return;
                        }

                        const option = select.selectedOptions[0];
                        const root = select.closest('[data-official-preparer]');

                        syncOfficialPreparer(root, {
                            id: option.value,
                            name: option.dataset.name,
                            email: option.dataset.email,
                            title: option.dataset.title,
                            initials: option.dataset.initials,
                        }, 'Diwakilkan');
                    });
                })();
            </script>
        @endonce
    @endif
</x-layouts::app>
