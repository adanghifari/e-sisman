<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_departments_and_baseline_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['ITMS', 'HCGA', 'PMKT', 'HSSE', 'MOPS', 'SDD'] as $departmentCode) {
            $this->assertTrue(Department::query()->where('kode_department', $departmentCode)->exists());
        }

        $userRole = Role::query()->where('nama_role', 'User')->firstOrFail();
        $documentControlRole = Role::query()->where('nama_role', 'Admin Kontrol Dokumen')->firstOrFail();
        $superAdminRole = Role::query()->where('nama_role', 'SuperAdmin')->firstOrFail();

        $allPermissionIds = Permission::query()->pluck('id')->sort()->values()->all();
        $this->assertSame($allPermissionIds, $superAdminRole->permissions()->pluck('permissions.id')->sort()->values()->all());

        $this->assertTrue($documentControlRole->permissions()->where('module', 'Manajemen Dokumen')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('module', 'Administrasi')->where('action', 'view')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('module', 'Administrasi')->where('action', 'create')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('module', 'Administrasi')->where('action', 'update')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('module', 'Administrasi')->where('action', 'delete')->exists());

        $this->assertTrue($userRole->permissions()->where('code', 'documents.master.view')->exists());
        $this->assertFalse($userRole->permissions()->where('code', 'documents.approval.assign')->exists());

        $this->assertTrue(User::query()->where('email', 'admin.kontrol@example.com')->firstOrFail()->roles()->whereKey($documentControlRole->id)->exists());
        $this->assertTrue(User::query()->where('email', 'superadmin@example.com')->firstOrFail()->roles()->whereKey($superAdminRole->id)->exists());
    }
}
