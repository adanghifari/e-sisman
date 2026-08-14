<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\DocumentTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_editor_can_upload_independent_active_templates_per_level(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => 'Template Level Satu',
                'notes' => 'Catatan level satu.',
                'template_files' => [
                    UploadedFile::fake()->create('level-satu.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Level Dua',
                'notes' => 'Catatan level dua.',
                'template_files' => [
                    UploadedFile::fake()->create('level-dua.doc', 28, 'application/msword'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $levelOne = DocumentTemplate::query()->forLevel('level-1')->active()->with('files')->firstOrFail();
        $levelTwo = DocumentTemplate::query()->forLevel('level-2')->active()->with('files')->firstOrFail();

        $this->assertSame('Template Level Satu', $levelOne->title);
        $this->assertSame('Template Level Dua', $levelTwo->title);
        $this->assertSame('level-satu.docx', $levelOne->files->first()->original_file_name);
        $this->assertSame('level-dua.doc', $levelTwo->files->first()->original_file_name);
        Storage::disk('local')->assertExists($levelOne->files->first()->path_file);
        Storage::disk('local')->assertExists($levelTwo->files->first()->path_file);
    }

    public function test_template_page_renders_saved_title_notes_and_file_after_reload(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-3',
                'title' => 'Template Instruksi Kerja',
                'notes' => 'Gunakan untuk dokumen instruksi kerja.',
                'template_files' => [
                    UploadedFile::fake()->create('instruksi-kerja.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->get(route('document-templates.index'))
            ->assertOk()
            ->assertSee('Template Instruksi Kerja')
            ->assertSee('Gunakan untuk dokumen instruksi kerja.')
            ->assertSee('instruksi-kerja.docx');
    }

    public function test_template_viewer_cannot_upload_template(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $viewer = User::factory()->create();
        $viewerRole = Role::query()->create(['nama_role' => 'Template Viewer']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();

        $viewerRole->permissions()->sync([$viewPermission->id]);
        $viewerRole->users()->sync([$viewer->id]);

        $this->actingAs($viewer)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => 'Template Ditolak',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertForbidden();
    }

    public function test_template_upload_requires_title_and_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->from(route('document-templates.index'))
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => '',
                'template_files' => [],
            ])
            ->assertRedirect(route('document-templates.index'))
            ->assertSessionHasErrors(['title', 'template_files']);

        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    private function templateEditor(): User
    {
        $editor = User::factory()->create();
        $editorRole = Role::query()->create(['nama_role' => 'Template Editor']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();
        $editPermission = Permission::query()->where('code', 'document-templates.edit')->firstOrFail();

        $editorRole->permissions()->sync([$viewPermission->id, $editPermission->id]);
        $editorRole->users()->sync([$editor->id]);

        return $editor->refresh();
    }
}
