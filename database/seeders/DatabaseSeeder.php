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
            DocumentTypeSeeder::class,
        ]);

        DB::table('departments')->updateOrInsert([
            'kode_department' => 'DEFAULT',
        ], [
            'nama_department' => 'Default Department',
        ]);

        User::factory()->create([
            'm_department_id' => DB::table('departments')->where('kode_department', 'DEFAULT')->value('id'),
            'nik' => '000000',
            'nama' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);
    }
}
