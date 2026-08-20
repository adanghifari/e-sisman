<x-layouts::app :title="__('Tambah Dokumen')">
    @php
        $documentLevels = collect(config('document-levels'))
            ->except('level-4')
            ->all();
    @endphp

    <div class="space-y-20">
        <x-ui.page-header title="Tambah Dokumen" />

        <div class="mx-auto grid w-full max-w-4xl gap-5">
            @foreach ($documentLevels as $levelKey => $level)
                <x-documents.level-card
                    :level="$level['badge']"
                    :title="$level['name']"
                    :description="$level['create_description']"
                    :href="route('documents.create.level', $levelKey)"
                />
            @endforeach
        </div>
    </div>
</x-layouts::app>
