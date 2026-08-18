<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        'ITSM' => 'IT & Management System Department',
        'HCGA' => 'Human Capital & GA Department',
        'PMKT' => 'Port Marketing Department',
        'HSSE' => 'HSSE Department',
        'MOPS' => 'Marine Operation Department',
        'SDD' => 'Strategic Development Department',
    ];

    public function run(): void
    {
        $this->normalizeItManagementSystemDepartment();
        $this->pruneUnusedDefaultDepartment();

        foreach (self::DEPARTMENTS as $code => $name) {
            DB::table('departments')->updateOrInsert(
                ['kode_department' => $code],
                ['nama_department' => $name],
            );
        }
    }

    private function normalizeItManagementSystemDepartment(): void
    {
        $legacyDepartmentId = DB::table('departments')->where('kode_department', 'ITMS')->value('id');
        $targetDepartmentId = DB::table('departments')->where('kode_department', 'ITSM')->value('id');

        if ($legacyDepartmentId === null) {
            return;
        }

        if ($targetDepartmentId === null) {
            DB::table('departments')
                ->where('id', $legacyDepartmentId)
                ->update([
                    'kode_department' => 'ITSM',
                    'nama_department' => self::DEPARTMENTS['ITSM'],
                ]);

            return;
        }

        DB::table('users')
            ->where('m_department_id', $legacyDepartmentId)
            ->update(['m_department_id' => $targetDepartmentId]);

        $documentIds = DB::table('document_departments')
            ->where('department_id', $legacyDepartmentId)
            ->pluck('t_document_id');

        foreach ($documentIds as $documentId) {
            DB::table('document_departments')->updateOrInsert([
                't_document_id' => $documentId,
                'department_id' => $targetDepartmentId,
            ]);
        }

        DB::table('document_departments')->where('department_id', $legacyDepartmentId)->delete();
        DB::table('departments')->where('id', $legacyDepartmentId)->delete();
    }

    private function pruneUnusedDefaultDepartment(): void
    {
        $defaultDepartmentId = DB::table('departments')->where('kode_department', 'DEFAULT')->value('id');

        if ($defaultDepartmentId === null) {
            return;
        }

        $isUsedByUser = DB::table('users')->where('m_department_id', $defaultDepartmentId)->exists();
        $isUsedByDocument = DB::table('document_departments')->where('department_id', $defaultDepartmentId)->exists();

        if (! $isUsedByUser && ! $isUsedByDocument) {
            DB::table('departments')->where('id', $defaultDepartmentId)->delete();
        }
    }
}
