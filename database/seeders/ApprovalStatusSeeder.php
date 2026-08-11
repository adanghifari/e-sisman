<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['PENDING', 'APPROVED', 'REJECTED', 'TERMINATED'] as $kode_status) {
            DB::table('m_approval_status')->updateOrInsert(
                ['kode_status' => $kode_status],
                ['nama_status' => $kode_status],
            );
        }
    }
}
