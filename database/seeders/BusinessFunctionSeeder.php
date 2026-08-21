<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        $businessFunctions = [
            ['kode' => 'OPS', 'nama_proses_fungsi' => 'Operasional'],
            ['kode' => 'QA', 'nama_proses_fungsi' => 'Quality Assurance'],
            ['kode' => 'HSSE', 'nama_proses_fungsi' => 'Health, Safety, Security, and Environment'],
            ['kode' => 'HCGA', 'nama_proses_fungsi' => 'Human Capital and General Affairs'],
            ['kode' => 'IT', 'nama_proses_fungsi' => 'Information Technology'],
        ];

        foreach ($businessFunctions as $businessFunction) {
            DB::table('m_proses_fungsi')->updateOrInsert(
                ['kode' => $businessFunction['kode']],
                [
                    'nama_proses_fungsi' => $businessFunction['nama_proses_fungsi'],
                    'is_active' => true,
                ],
            );
        }
    }
}
