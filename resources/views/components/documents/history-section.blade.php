@props([
    'documentHistory' => collect(),
])

<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-5">
        <h3 class="text-sm font-bold text-slate-900">Riwayat Dokumen</h3>
    </div>
    <div class="max-h-80 space-y-2 overflow-y-auto px-6 py-5 app-scrollbar">
        @forelse ($documentHistory as $history)
            @php
                $historyTimestamp = $history['timestamp'];
            @endphp
            <div class="rounded-lg bg-slate-50 px-3 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">{{ $history['description'] }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            {{ $history['document_number'] }} - Revisi {{ $history['revision'] }}
                        </p>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            {{ $historyTimestamp ? $historyTimestamp->translatedFormat('d M Y H:i:s') : 'Belum tercatat' }}
                        </p>
                    </div>
                    <x-ui.status-badge
                        :label="$history['label']"
                        :tone="$historyTimestamp ? $history['tone'] : 'slate'"
                        class="shrink-0"
                    />
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                Belum ada riwayat dokumen.
            </p>
        @endforelse
    </div>
</section>
