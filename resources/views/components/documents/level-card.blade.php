@props([
    'title',
    'description',
    'href' => null,
])

<x-ui.panel :title="$title" :padded="false" class="flex min-h-[260px] flex-col">
    <div class="flex flex-1 flex-col justify-between gap-8 p-6">
        <p class="max-w-2xl text-base leading-8 text-slate-700 md:text-lg">
            {{ $description }}
        </p>

        <div>
            <x-ui.action-button :href="$href" class="min-w-40">
                Ajukan Dokumen
            </x-ui.action-button>
        </div>
    </div>
</x-ui.panel>
