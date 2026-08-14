<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DocumentStatusSeeder::class,
            ApprovalStatusSeeder::class,
            DocumentLevelSeeder::class,
            DocumentTypeSeeder::class,
            PermissionSeeder::class,
        ]);

        DB::table('departments')->updateOrInsert([
            'kode_department' => 'DEFAULT',
        ], [
            'nama_department' => 'Default Department',
        ]);

        foreach (['Superadmin', 'Admin Kontrol Dokumen', 'User'] as $role) {
            DB::table('roles')->updateOrInsert(
                ['nama_role' => $role],
                ['nama_role' => $role],
            );
        }

        $departmentId = DB::table('departments')->where('kode_department', 'DEFAULT')->value('id');

        User::updateOrCreate(
            ['nik' => '000000'],
            [
                'm_department_id' => $departmentId,
                'name' => 'Developer',
                'email' => 'developer@example.com',
                'password' => 'Password123!',
            ],
        );

        $users = [
            ['nik' => '0000111', 'name' => 'Muhammad Akhdan Ghifari', 'email' => 'akhdan@example.com', 'jabatan' => 'Manager'],
            ['nik' => '0000112', 'name' => 'Muhammad Azigha Azhar', 'email' => 'azigha.lestari@example.com', 'jabatan' => 'Manager'],
            ['nik' => '0000113', 'name' => 'Hafiz Fawwaz Aydil', 'email' => 'aydil@example.com', 'jabatan' => 'Business Process Analyst'],
            ['nik' => '0000114', 'name' => 'Nadia Putri', 'email' => 'nadia.putri@example.com', 'jabatan' => 'Quality Assurance Officer'],
            ['nik' => '0000115', 'name' => 'Dimas Pratama', 'email' => 'dimas.pratama@example.com', 'jabatan' => 'Operations Supervisor'],
            ['nik' => '0000116', 'name' => 'Farhan Selatan', 'email' => 'parhan@example.com', 'jabatan' => 'Compliance Officer'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['nik' => $user['nik']],
                [
                    'm_department_id' => $departmentId,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => 'Password123!',
                    'jabatan' => $user['jabatan'],
                ],
            );
        }
    }
}
