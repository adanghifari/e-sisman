<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessProcessSeeder extends Seeder
{
    public function run(): void
    {
        $businessProcesses = [
            ['kode' => 'SMR', 'nama_proses_bisnis' => 'Sistem Manajemen Risiko'],
            ['kode' => 'KSA', 'nama_proses_bisnis' => 'Komersial dan Strategi Area'],
            ['kode' => 'MRI', 'nama_proses_bisnis' => 'Manajemen Risiko Industri'],
        ];

        foreach ($businessProcesses as $businessProcess) {
            DB::table('m_proses_bisnis')->updateOrInsert(
                ['kode' => $businessProcess['kode']],
                [
                    'nama_proses_bisnis' => $businessProcess['nama_proses_bisnis'],
                    'is_active' => true,
                ],
            );
        }
    }
}
