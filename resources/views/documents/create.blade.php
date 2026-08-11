<x-layouts::app :title="__('Tambah Dokumen')">
    @php
        $documentLevels = [
            [
                'title' => 'Dokumen Level I : Manual SKMBS',
                'description' => 'Dokumen utama yang umumnya menjelaskan dan memberikan informasi tentang ruang lingkup Sistem Manajemen terintegrasi sesuai persyaratan ISO dan PP No. 50 tahun 2012, proses bisnis, diagram hubungan antar fungsi dan penjelasan fungsi.',
            ],
            [
                'title' => 'Dokumen Level II : Prosedur SKMBS',
                'description' => 'Dokumen yang menjabarkan proses didalam context diagram yang lebih jelas terhadap pemenuhan persyaratan Sistem Manajemen KBS yang terkait dengan fungsi-fungsi kegiatan bisnis Perusahaan dalam dokumen level I (Manual).',
            ],
            [
                'title' => 'Dokumen Level III : Instruksi Kerja (Internal)',
                'description' => 'Dokumen yang menguraikan secara rinci tahapan teknis dalam pelaksanaan suatu kegiatan yang tertuang didalam dokumen level II dan tidak hanya garis besar saja namun menjelaskan instruksi pekerjaan dari awal sampai akhir.',
            ],
            [
                'title' => 'Dokumen Level III : Instruksi Kerja (Lintas Department)',
                'description' => 'Dokumen yang menguraikan secara rinci tahapan teknis dalam pelaksanaan suatu kegiatan yang tertuang didalam dokumen level II dan tidak hanya garis besar saja namun menjelaskan instruksi pekerjaan dari awal sampai akhir.',
            ],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Tambah Dokumen" />

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($documentLevels as $level)
                <x-documents.level-card
                    :title="$level['title']"
                    :description="$level['description']"
                    href="#"
                />
            @endforeach
        </div>
    </div>
</x-layouts::app>
