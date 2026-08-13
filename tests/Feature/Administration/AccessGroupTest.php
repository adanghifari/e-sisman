<?php

namespace Tests\Feature\Administration;

use App\Livewire\Administration\AccessGroup\Index as AccessGroupIndex;
use App\Livewire\Administration\AccessMenu\Index as AccessMenuIndex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_group_can_assign_permissions_and_users(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'test@example.com']);
        $member = User::factory()->create(['name' => 'Document Controller']);
        $permission = Permission::query()->where('code', 'documents.inbox.view')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(AccessGroupIndex::class)
            ->call('create')
            ->set('nama_role', 'Document Control')
            ->set('permissionIds', [$permission->id])
            ->set('userIds', [$member->id])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::query()->where('nama_role', 'Document Control')->firstOrFail();

        $this->assertTrue($role->permissions()->where('code', 'documents.inbox.view')->exists());
        $this->assertTrue($role->users()->where('users.id', $member->id)->exists());
        $this->assertTrue($member->fresh()->hasPermission('documents.inbox.view'));
    }

    public function test_access_group_picker_can_add_multiple_users(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'test@example.com']);
        $firstUser = User::factory()->create(['name' => 'First Member']);
        $secondUser = User::factory()->create(['name' => 'Second Member']);

        $this->actingAs($admin);

        Livewire::test(AccessGroupIndex::class)
            ->call('create')
            ->call('addUserFromPicker', $firstUser->id)
            ->call('addUserFromPicker', $secondUser->id)
            ->call('addUserFromPicker', $firstUser->id)
            ->assertSet('userIds', [$firstUser->id, $secondUser->id]);
    }

    public function test_access_group_can_be_saved_without_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'test@example.com']);
        $member = User::factory()->create(['name' => 'Member Without Access']);

        $this->actingAs($admin);

        Livewire::test(AccessGroupIndex::class)
            ->call('create')
            ->set('nama_role', 'Group Tanpa Akses')
            ->call('addUserFromPicker', $member->id)
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::query()->where('nama_role', 'Group Tanpa Akses')->firstOrFail();

        $this->assertSame(0, $role->permissions()->count());
        $this->assertTrue($role->users()->where('users.id', $member->id)->exists());
    }

    public function test_access_menu_page_lists_permission_catalog(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->actingAs(User::factory()->create(['email' => 'test@example.com']));

        Livewire::test(AccessMenuIndex::class)
            ->assertSee('Lihat Inbox Approval')
            ->assertSee('documents.inbox.view');
    }

    public function test_assigned_user_cannot_open_route_without_group_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $role = Role::create(['nama_role' => 'Inbox Only']);
        $permission = Permission::query()->where('code', 'documents.inbox.view')->firstOrFail();

        $role->permissions()->sync([$permission->id]);
        $role->users()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('documents.inbox'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_seeded_administrator_identity_bypasses_group_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create([
            'nik' => '000000',
            'name' => 'Administrator',
            'email' => 'administrator@example.com',
        ]);
        $role = Role::create(['nama_role' => 'No Access']);

        $role->users()->sync([$admin->id]);

        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->hasPermission('reports.view'));

        $this->actingAs($admin->fresh())
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_template_viewer_can_open_template_page_without_edit_button(): void
    {
        $this->seed(PermissionSeeder::class);

        $viewer = User::factory()->create();
        $viewerRole = Role::create(['nama_role' => 'Template Viewer']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();

        $viewerRole->permissions()->sync([$viewPermission->id]);
        $viewerRole->users()->sync([$viewer->id]);

        $response = $this->actingAs($viewer->fresh())
            ->get(route('document-templates.index'))
            ->assertOk();

        $this->assertStringNotContainsString('data-template-edit-toggle="data-template-edit-toggle"', $response->getContent());
    }

    public function test_template_editor_can_see_template_edit_button(): void
    {
        $this->seed(PermissionSeeder::class);

        $editor = User::factory()->create();
        $editorRole = Role::create(['nama_role' => 'Template Editor']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();
        $editPermission = Permission::query()->where('code', 'document-templates.edit')->firstOrFail();

        $editorRole->permissions()->sync([$viewPermission->id, $editPermission->id]);
        $editorRole->users()->sync([$editor->id]);

        $this->assertTrue($editor->fresh()->hasAnyPermission(['document-templates.edit']));

        $response = $this->actingAs($editor->fresh())
            ->get(route('document-templates.index'))
            ->assertOk();

        $this->assertStringContainsString('data-template-edit-toggle="data-template-edit-toggle"', $response->getContent());
    }
}
