<?php

namespace Tests\Feature\Administration;

use App\Livewire\Administration\User\Index;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_page_can_be_rendered(): void
    {
        User::factory()->create([
            'name' => 'Nadia Putri',
            'nik' => '0000114',
            'jabatan' => 'Quality Assurance Officer',
            'no_whatsapp' => '628123456789',
        ]);

        Livewire::test(Index::class)
            ->assertSee('List User')
            ->assertSee('Nadia Putri')
            ->assertSee('Quality Assurance Officer')
            ->assertSee('628123456789');
    }

    public function test_user_can_be_updated_from_management_page(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $role = Role::create(['nama_role' => 'Admin Kontrol Dokumen']);
        $user = User::factory()->create([
            'm_department_id' => null,
            'jabatan' => null,
            'no_whatsapp' => null,
            'is_active' => true,
        ]);

        Livewire::test(Index::class)
            ->call('edit', $user->id)
            ->set('m_department_id', (string) $department->id)
            ->set('role_id', (string) $role->id)
            ->set('jabatan', 'Document Controller')
            ->set('no_whatsapp', '628765432100')
            ->set('is_active', false)
            ->call('save')
            ->assertSet('showForm', false);

        $user->refresh();

        $this->assertSame($department->id, $user->m_department_id);
        $this->assertSame('Document Controller', $user->jabatan);
        $this->assertSame('628765432100', $user->no_whatsapp);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
    }
}
