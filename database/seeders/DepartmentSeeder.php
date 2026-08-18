<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        'ITMS' => 'IT & Management System Department',
        'HCGA' => 'Human Capital & GA Department',
        'PMKT' => 'Port Marketing Department',
        'HSSE' => 'HSSE Department',
        'MOPS' => 'Marine Operation Department',
        'SDD' => 'Strategic Development Department',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $code => $name) {
            DB::table('departments')->updateOrInsert(
                ['kode_department' => $code],
                ['nama_department' => $name],
            );
        }
    }
}
