<x-layouts::app :title="__('Import Dokumen Obsolete')">
    <div class="space-y-20">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700" wire:navigate>Home</a>
                    <flux:icon name="chevron-right" class="size-4 text-slate-400" />
                    <a href="{{ route('documents.obsolete') }}" class="transition hover:text-sky-700" wire:navigate>Dokumen Obsolete</a>
                    <flux:icon name="chevron-right" class="size-4 text-slate-400" />
                    <span class="text-slate-700">Import Dokumen Obsolete</span>
                </nav>
                <x-ui.page-header title="Import Dokumen Obsolete" description="Pilih ketentuan arsip obsolete yang akan diimport." />
            </div>
        </div>

        <div class="mx-auto grid w-full max-w-4xl gap-5">
            @foreach ($documentLevels as $levelKey => $level)
                <x-documents.level-card
                    :level="$level['badge']"
                    :title="$level['name']"
                    :description="$level['create_description']"
                    :href="route('documents.obsolete.imports.create.level', $levelKey)"
                    action-label="Import Obsolete"
                />
            @endforeach

            <section class="group overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                <div class="grid gap-5 border-l-4 border-amber-500 px-5 py-6 md:grid-cols-[88px_minmax(0,1fr)_auto] md:items-center md:px-7 md:py-7">
                    <div class="flex items-start pt-1 md:justify-center md:pt-0">
                        <div class="flex size-16 items-center justify-center rounded-lg bg-white text-center text-xs font-bold uppercase leading-tight text-amber-700 ring-1 ring-amber-100">
                            Lama
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-950">
                            Mengikuti Ketentuan Dokumen Lama
                        </h2>
                        <p class="mt-2 max-w-3xl text-base leading-7 text-slate-600">
                            Gunakan pilihan ini kalau arsip obsolete tidak mengikuti struktur level, proses, fungsi, dan department pada workflow sistem saat ini.
                        </p>
                    </div>

                    <div class="flex items-center md:justify-end">
                        <a
                            href="{{ route('documents.obsolete.imports.create.legacy') }}"
                            class="inline-flex h-10 w-full min-w-40 items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 md:w-auto"
                            wire:navigate
                        >
                            Import Legacy
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
