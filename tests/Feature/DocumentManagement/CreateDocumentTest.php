<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DocumentRejectionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $permissions = collect([
            [
                'code' => 'documents.create.view',
                'name' => 'Lihat Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.create',
                'name' => 'Submit Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.store',
                'action' => 'create',
            ],
            [
                'code' => 'documents.create.drafts',
                'name' => 'Lihat Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.drafts.edit',
                'name' => 'Edit Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts.edit',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.drafts.delete',
                'name' => 'Hapus Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts.destroy',
                'action' => 'delete',
            ],
            [
                'code' => 'documents.master.view',
                'name' => 'Lihat Dokumen Master',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master',
                'action' => 'view',
            ],
            [
                'code' => 'documents.master.detail',
                'name' => 'Lihat Detail Dokumen Master',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
            [
                'code' => 'documents.inbox.view',
                'name' => 'Lihat Inbox Approval',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.inbox',
                'action' => 'view',
            ],
        ])->map(fn (array $permission): Permission => Permission::query()->firstOrCreate(
            ['code' => $permission['code']],
            $permission,
        ));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
    }

    public function test_level_three_create_page_is_displayed(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('Nama Dokumen')
            ->assertDontSee('Assign Approver')
            ->assertSee('Test User');
    }

    public function test_level_three_create_page_lists_active_master_procedure_reference(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'KSA',
            'nama_proses_bisnis' => 'Kesisteman',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $oldProcedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
        ]);

        Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'revised_from' => $oldProcedure->id,
            'nama_dokumen' => 'Prosedur Aktif Revisi',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 1,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('PS-KSA-02 - Prosedur Aktif Revisi')
            ->assertDontSee('PS-KSA-02 - Prosedur Lama');
    }

    public function test_level_three_create_page_lists_approved_revision_request_as_active_procedure_reference(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'KSA',
            'nama_proses_bisnis' => 'Kondisi Solusi Abadi',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'KTL',
            'nama_proses_fungsi' => 'Koefisiensi Terima Literasi',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();

        $sourceProcedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Sebelum Revisi',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
        ]);

        Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'revised_from' => $sourceProcedure->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Sesudah Revisi',
            'nomor_dokumen' => 'FMPS-KSA-02',
            'nomor_revisi' => 1,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('PS-KSA-02 - Prosedur Sesudah Revisi')
            ->assertDontSee('FMPS-KSA-02 - Prosedur Sesudah Revisi');
    }

    public function test_level_two_create_page_uses_integrated_create_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Level Two User',
            'email' => 'level-two@example.com',
        ]);
        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('Tambah Dokumen Level II')
            ->assertSee('Nama Dokumen')
            ->assertSee('SMR - Sistem Manajemen Risiko')
            ->assertSee('OPS - Operasional')
            ->assertDontSee('Level Dokumen:')
            ->assertSee('Penyusun Pemilik Proses')
            ->assertSee('Template Dokumen yang Sudah Diisi')
            ->assertSee('Level Two User');
    }

    public function test_level_one_create_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-1'))
            ->assertOk()
            ->assertSee('Import Dokumen Level I')
            ->assertSee('Nama Dokumen')
            ->assertSee('Upload Dokumen')
            ->assertSee('Import Dokumen');
    }

    public function test_create_document_sidebar_stays_active_on_level_forms(): void
    {
        $user = User::factory()->create();

        foreach (['level-1', 'level-2', 'level-3'] as $level) {
            $this->actingAs($user)
                ->get(route('documents.create.level', $level))
                ->assertOk()
                ->assertSee('bg-white text-sky-800 shadow-sm', false)
                ->assertSee('Tambah Dokumen');
        }
    }

    public function test_level_one_document_can_be_imported(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'Manual']);

        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-1'), [
                'nama_dokumen' => 'Manual SKMBS',
                'nomor_dokumen_suffix' => '001',
                'nomor_revisi' => '00.00',
                'tanggal_terbit' => '2026-08-12',
                'catatan_revisi' => 'Dokumen awal.',
                'imported_document' => UploadedFile::fake()->create('manual.pdf', 24, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Manual SKMBS', $document->nama_dokumen);
        $this->assertSame('SM-001', $document->nomor_dokumen);
        $this->assertSame(0, $document->nomor_revisi);
        $this->assertSame('Dokumen awal.', $document->catatan_revisi);
        $this->assertTrue($document->files()->where('type_file', 'imported_document')->exists());
    }

    public function test_level_two_document_can_be_saved_as_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'Prosedur']);

        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Prosedur Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '002',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Prosedur Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertSame('PS-OPS-002', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
    }

    public function test_user_can_list_and_continue_own_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->value('id'),
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Prosedur Saya',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);
        $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/1/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->value('id'),
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $otherUser->id,
            'nama_dokumen' => 'Draft User Lain',
            'nomor_dokumen' => 'PS-SMR-011',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create'))
            ->assertOk()
            ->assertSee('Draft Saya')
            ->assertSee('1');

        $this->actingAs($user)
            ->get(route('documents.create.drafts'))
            ->assertOk()
            ->assertSee('Draft Prosedur Saya')
            ->assertDontSee('Draft User Lain');

        $this->actingAs($user)
            ->get(route('documents.create.drafts.edit', $draft))
            ->assertOk()
            ->assertSee('Draft Prosedur Saya')
            ->assertSee('010')
            ->assertSee('template.pdf')
            ->assertSee('data-existing-file-item', false)
            ->assertSee('Tanpa perwakilan');
    }

    public function test_saving_existing_draft_updates_same_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Nama Lama',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Nama Baru Draft',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '012',
                'filled_template' => UploadedFile::fake()->create('template-baru.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertSame(1, Document::query()->count());

        $draft->refresh();
        $this->assertSame('Nama Baru Draft', $draft->nama_dokumen);
        $this->assertSame('PS-OPS-012', $draft->nomor_dokumen);
        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertTrue($draft->files()->where('original_file_name', 'template-baru.pdf')->exists());
    }

    public function test_user_can_delete_own_draft_from_draft_list(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Akan Dihapus',
            'nomor_dokumen' => 'PS-SMR-016',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        Storage::disk('local')->put('documents/'.$draft->id.'/template.pdf', 'dummy');
        $file = $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.drafts'))
            ->assertOk()
            ->assertSee('Draft Akan Dihapus')
            ->assertSee('Hapus')
            ->assertSee(route('documents.create.drafts.destroy', $draft), false);

        $this->actingAs($user)
            ->delete(route('documents.create.drafts.destroy', $draft))
            ->assertRedirect(route('documents.create.drafts'))
            ->assertSessionHas('status', 'Draft berhasil dihapus.');

        $this->assertDatabaseMissing('t_document', [
            'id' => $draft->id,
        ]);
        $this->assertDatabaseMissing('t_document_files', [
            'id' => $file->id,
        ]);
        $this->assertDatabaseMissing('document_departments', [
            't_document_id' => $draft->id,
            'department_id' => $department->id,
        ]);
        Storage::disk('local')->assertMissing('documents/'.$draft->id.'/template.pdf');
    }

    public function test_user_cannot_delete_another_users_draft(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $otherUser->id,
            'nama_dokumen' => 'Draft User Lain',
            'nomor_dokumen' => 'PS-SMR-017',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('documents.create.drafts.destroy', $draft))
            ->assertForbidden();

        $this->assertDatabaseHas('t_document', [
            'id' => $draft->id,
        ]);
    }

    public function test_saving_existing_draft_can_remove_saved_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Dengan File',
            'nomor_dokumen' => 'PS-SMR-015',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        Storage::disk('local')->put('documents/'.$draft->id.'/template.pdf', 'dummy');
        $file = $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Draft Dengan File',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '015',
                'remove_existing_files' => [$file->id],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertDatabaseMissing('t_document_files', [
            'id' => $file->id,
        ]);
        Storage::disk('local')->assertMissing('documents/'.$draft->id.'/template.pdf');
    }

    public function test_existing_draft_can_be_submitted_without_reuploading_saved_main_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Siap Submit',
            'nomor_dokumen' => 'PS-SMR-014',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);
        $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Draft Siap Submit',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '014',
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $this->assertSame(1, Document::query()->count());

        $draft->refresh();
        $this->assertSame(StatusDocument::PROPOSED, $draft->status->nama_status);
        $this->assertNotNull($draft->submitted_at);
        $this->assertTrue($draft->approvals()
            ->where('user_id', $user->id)
            ->where('stages', 'TTD Penyusun Resmi')
            ->exists());
    }

    public function test_submitted_document_records_official_preparer_signature_without_stage_assignment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $officialPreparer = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => 'Menunggu',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::WAITING,
            'nama_status' => 'Menunggu Giliran',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::REJECTED,
            'nama_status' => 'Ditolak',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::TERMINATED,
            'nama_status' => 'Dihentikan',
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'Form']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Submit Approval',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '009',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $document = Document::query()->where('nama_dokumen', 'Prosedur Submit Approval')->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $document->status->nama_status);
        $this->assertSame($officialPreparer->id, $document->official_preparer_id);
        $this->assertTrue($document->approvals()
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'TTD Penyusun Resmi')
            ->whereNotNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::APPROVED))
            ->exists());

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Submit Approval');

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval')
            ->assertSee('TTD Penyusun Resmi')
            ->assertSee('Disetujui');

        $this->actingAs($officialPreparer)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval');

        $this->actingAs($user)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval')
            ->assertSee('Pengajuan Dokumen')
            ->assertSee(StatusDocument::PROPOSED);

        $this->actingAs($user)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval');
    }

    public function test_user_from_document_department_can_submit_revision_from_master_document(): void
    {
        Storage::fake('local');

        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $sourceDepartment = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $submitter = User::factory()->create(['m_department_id' => $sourceDepartment->id]);
        $officialPreparer = User::factory()->create();
        $otherUser = User::factory()->create(['m_department_id' => $otherDepartment->id]);
        $userRole = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $submitter->roles()->syncWithoutDetaching([$userRole->id]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        Permission::query()->firstOrCreate(
            ['code' => 'documents.create.level'],
            [
                'name' => 'Lihat Form Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.level',
                'action' => 'view',
            ],
        );
        Permission::query()->firstOrCreate(
            ['code' => 'documents.create.create'],
            [
                'name' => 'Submit Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.store',
                'action' => 'create',
            ],
        );
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => 'Menunggu',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::WAITING,
            'nama_status' => 'Menunggu Giliran',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::REJECTED,
            'nama_status' => 'Ditolak',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::TERMINATED,
            'nama_status' => 'Dihentikan',
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'Form']);
        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Prosedur Revisi Master',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'tanggal_terbit' => '2026-08-12',
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$sourceDepartment->id]);

        $this->actingAs($otherUser)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertForbidden();

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('Dokumen Level IV: Form Prosedur')
            ->assertSee('Import Dokumen Level II: Prosedur SKMBS')
            ->assertSee('Dokumen Revisi')
            ->assertSee('1. Isi Dokumen Versi Revisi')
            ->assertSee('2. Lembar Revisi')
            ->assertSee('Penyusun Pemilik Proses')
            ->assertSee('Pilih Penyusun Resmi')
            ->assertSee('FMPS')
            ->assertSee('SMR')
            ->assertSee('010')
            ->assertSee('00.01')
            ->assertSee('Quality Assurance')
            ->assertDontSee('Tambah Department')
            ->assertDontSee('-Pilih-');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $draftRevision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame(StatusDocument::DRAFT, $draftRevision->status->nama_status);

        $draftRevision->departments()->detach();
        $draftRevision->delete();

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'revision_content' => UploadedFile::fake()->create('dokumen-revisi.pdf', 24, 'application/pdf'),
                'revision_form' => UploadedFile::fake()->create('lembar-revisi.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
        $this->assertSame('level-4', $revision->documentLevel->kode);
        $this->assertSame('Form', $revision->documentType->nama_types);
        $this->assertSame(1, $revision->nomor_revisi);
        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertSame($officialPreparer->id, $revision->official_preparer_id);
        $this->assertSame($businessProcess->id, $revision->m_proses_bisnis_id);
        $this->assertSame($businessFunction->id, $revision->m_proses_fungsi_id);
        $this->assertTrue($revision->departments()->whereKey($sourceDepartment->id)->exists());
        $this->assertFalse($revision->departments()->whereKey($otherDepartment->id)->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_content')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_form')->exists());

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('Dokumen Revisi')
            ->assertSee('Isi Dokumen Versi Revisi')
            ->assertSee('Lembar Revisi')
            ->assertSee('dokumen-revisi.pdf')
            ->assertSee('lembar-revisi.pdf')
            ->assertDontSee('Assign Approver');

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Revisi Master Updated');

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Master Updated')
            ->assertSee('Pengajuan Revisi')
            ->assertSee('FMPS-SMR-010-01')
            ->assertSee(StatusDocument::PROPOSED);

        $documentControlRole = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $assignPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.assign'],
            [
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        );
        $documentControlRole->permissions()->syncWithoutDetaching([$assignPermission->id]);
        $documentControlAdmin = User::factory()->create([
            'm_department_id' => $sourceDepartment->id,
            'name' => 'Admin Kontrol Dokumen',
        ]);
        $documentControlAdmin->roles()->attach($documentControlRole);
        $approver = User::factory()->create(['name' => 'Approver Revisi']);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $source->m_document_level_id,
            'nama_flow' => 'Flow Revisi Prosedur',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Superintendent',
        ]);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Master Updated')
            ->assertSee('Form')
            ->assertSee('Belum assign approver')
            ->assertSee('Perlu Verifikasi Admin KD');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $revision), [
                'stage_approvers' => [
                    $stage->id => [$approver->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $this->assertSame('Revisi', $revision->documentType->nama_types);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Revisi Master Updated');

        $this->actingAs($submitter)
            ->from(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Kedua',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'revision_content' => UploadedFile::fake()->create('dokumen-revisi-2.pdf', 24, 'application/pdf'),
                'revision_form' => UploadedFile::fake()->create('lembar-revisi-2.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertSessionHasErrors(['revised_from']);

        $this->assertFalse(Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Kedua')
            ->exists());
    }

    public function test_obsolete_document_cannot_be_used_as_revision_source(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::create(['nama_types' => 'Prosedur']);

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Obsolete',
            'nomor_dokumen' => 'PS-SMR-OLD',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertNotFound();
    }

    public function test_active_revision_request_blocks_new_revision_creation(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $proposedStatus = StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Revisi Aktif',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-02',
            'nomor_revisi' => 2,
            'submitted_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->from(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Prosedur Revisi Baru',
            ]))
            ->assertRedirect(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertSessionHasErrors(['revised_from']);

        $this->assertFalse(Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Baru')
            ->exists());
    }

    public function test_rejected_revision_resubmission_reuses_revision_number_and_form_number(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $rejectedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Revisi Ditolak',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Prosedur Revisi Setelah Ditolak',
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Setelah Ditolak')
            ->firstOrFail();

        $this->assertSame($rejectedRevision->id, $revision->resubmitted_from);
        $this->assertSame(1, $revision->nomor_revisi);
        $this->assertSame('00.01', $revision->formatted_revision);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
    }

    public function test_multiple_rejected_revision_resubmissions_keep_same_revision_number(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $firstAttempt = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Ditolak Pertama',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now()->subDay(),
        ]);
        $secondAttempt = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'resubmitted_from' => $firstAttempt->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Ditolak Kedua',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('00.01')
            ->assertDontSee('00.02');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Setelah Ditolak Dua Kali',
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Revisi Setelah Ditolak Dua Kali')
            ->firstOrFail();

        $this->assertSame($secondAttempt->id, $revision->resubmitted_from);
        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame(1, $revision->nomor_revisi);
        $this->assertSame('00.01', $revision->formatted_revision);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
    }

    public function test_level_four_revision_from_work_instruction_uses_fmik_document_number(): void
    {
        $submitter = User::factory()->create();
        $officialPreparer = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'MRI',
            'nama_proses_bisnis' => 'Manajemen Risiko Industri',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $submitter->forceFill(['m_department_id' => $department->id])->save();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'IK']);
        DocumentType::create(['nama_types' => 'Form']);
        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'IK')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Instruksi Kerja Revisi Master',
            'nomor_dokumen' => 'IK-MRI-01-04',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('Dokumen Level IV: Form Instruksi Kerja')
            ->assertSee('FMIK')
            ->assertSee('MRI')
            ->assertSee('01')
            ->assertSee('04');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Instruksi Kerja Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Instruksi Kerja Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame('IK-MRI-01-04', $revision->nomor_dokumen);
        $this->assertSame('FMIK-MRI-01-04-01', $revision->nomor_lembar_revisi);
    }

    public function test_level_three_document_can_be_saved_as_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $secondDepartment = Department::create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operasional',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'IK']);

        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Pengujian',
            'nomor_dokumen' => 'PS-SMR-001',
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => $procedure->id,
                'department_ids' => [$department->id, $secondDepartment->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()
            ->where('nama_dokumen', 'Instruksi Kerja Pengujian')
            ->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Instruksi Kerja Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertSame($procedure->id, $document->reference);
        $this->assertSame('IK-SMR-001-001', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertTrue($document->departments()->whereKey($secondDepartment->id)->exists());
    }

    public function test_level_three_draft_keeps_uploaded_file_without_defaulting_official_preparer(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Instruksi Kerja']);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Acuan',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Draft IK Dengan File',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => $procedure->id,
                'department_ids' => [$department->id],
                'nomor_dokumen_suffix' => '020',
                'filled_template' => UploadedFile::fake()->create('template-draft.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()
            ->where('nama_dokumen', 'Draft IK Dengan File')
            ->firstOrFail();

        $this->assertNull($document->official_preparer_id);
        $this->assertTrue($document->files()
            ->where('type_file', 'filled_template')
            ->where('original_file_name', 'template-draft.pdf')
            ->exists());
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors([
                'nama_dokumen',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
                'reference',
                'department_ids',
                'official_preparer_id',
                'nomor_dokumen_suffix',
                'filled_template',
            ]);
    }

    public function test_level_three_empty_draft_can_be_saved(): void
    {
        $user = User::factory()->create();
        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Instruksi Kerja']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame('Draft tanpa judul', $document->nama_dokumen);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertNull($document->official_preparer_id);
        $this->assertNull($document->nomor_dokumen);
    }

    public function test_level_three_reference_must_match_selected_process_and_function(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $otherBusinessProcess = BusinessProcess::create([
            'kode' => 'OPS',
            'nama_proses_bisnis' => 'Operasional',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'IK']);

        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $otherBusinessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Operasional',
            'nomor_dokumen' => 'PS-OPS-001',
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => $procedure->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['reference']);
    }

    public function test_document_number_must_be_unique(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-QA-001',
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Baru',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_rejected_initial_submission_number_can_be_reused_with_resubmission_chain(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $previous = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-003', [
            'm_status_document_id' => $rejectedStatus->id,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '003'))
            ->assertRedirect(route('documents.create'));

        $newDocument = Document::query()
            ->where('nomor_dokumen', 'PS-QA-003')
            ->whereKeyNot($previous->id)
            ->firstOrFail();

        $this->assertSame($previous->id, $newDocument->resubmitted_from);
        $this->assertSame(StatusDocument::REJECTED, $previous->refresh()->status->nama_status);
    }

    public function test_multiple_rejected_initial_submissions_keep_immediate_chain_and_history_notes(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $attempts = collect();
        $previous = null;

        foreach (range(1, 4) as $index) {
            $attempt = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-004', [
                'nama_dokumen' => "Attempt {$index}",
                'resubmitted_from' => $previous?->id,
            ], "Catatan penolakan {$index}");

            $attempts->push($attempt);
            $previous = $attempt;
        }

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '004'))
            ->assertRedirect(route('documents.create'));

        $active = Document::query()
            ->where('nomor_dokumen', 'PS-QA-004')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED))
            ->firstOrFail();

        $this->assertSame($attempts->last()->id, $active->resubmitted_from);
        $this->assertCount(5, Document::query()->where('nomor_dokumen', 'PS-QA-004')->get());

        $history = app(DocumentRejectionHistory::class)->forDocument($active);

        $this->assertSame(
            ['Catatan penolakan 1', 'Catatan penolakan 2', 'Catatan penolakan 3', 'Catatan penolakan 4'],
            $history->pluck('catatan')->all(),
        );
        $this->assertSame($attempts->pluck('id')->all(), $history->pluck('document_id')->all());

        $this->actingAs($user)
            ->get(route('documents.approval.show', $active))
            ->assertOk()
            ->assertSee('Pengajuan ulang dibuat dari transaksi #'.$attempts->last()->id)
            ->assertSee('Catatan penolakan 4');
    }

    public function test_active_document_number_duplicate_is_blocked(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $proposedStatus = StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Aktif',
            'nomor_dokumen' => 'PS-QA-005',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '005'))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_master_document_number_duplicate_is_blocked_even_if_rejected_attempt_exists(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Master',
            'nomor_dokumen' => 'PS-QA-006',
            'approved_at' => now(),
        ]);
        $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-006');

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '006'))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_rejection_history_does_not_mix_same_number_outside_resubmission_chain(): void
    {
        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $unrelated = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-SMR-007', [
            'nama_dokumen' => 'Unrelated',
        ], 'Catatan tidak boleh ikut');
        $chainAttempt = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-SMR-007', [
            'nama_dokumen' => 'Chain',
        ], 'Catatan chain');
        $active = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Active',
            'nomor_dokumen' => 'PS-SMR-007',
            'resubmitted_from' => $chainAttempt->id,
        ]);

        $history = app(DocumentRejectionHistory::class)->forDocument($active);

        $this->assertSame(['Catatan chain'], $history->pluck('catatan')->all());
        $this->assertNotContains($unrelated->id, $history->pluck('document_id')->all());
    }

    public function test_template_upload_must_be_pdf_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.docx', 24),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['filled_template']);
    }

    public function test_attachments_must_be_pdf_documents(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.xlsx', 24),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['attachments.0']);
    }

    public function test_attachment_title_is_stored_with_uploaded_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachment_titles' => ['Catatan Brainstorming'],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertDatabaseHas('t_document_files', [
            'type_file' => 'attachment',
            'attachment_title' => 'Catatan Brainstorming',
            'original_file_name' => 'lampiran.pdf',
        ]);
    }

    public function test_matching_attachment_upload_is_not_stored_twice(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $payload = [
            'nama_dokumen' => 'Instruksi Kerja Pengujian',
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'department_ids' => [$department->id],
            'official_preparer_id' => $user->id,
            'nomor_dokumen_suffix' => '001',
            'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
            'attachment_titles' => ['Catatan Brainstorming'],
            'attachments' => [
                UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
            ],
            'submit_action' => 'draft',
        ];

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), $payload)
            ->assertRedirect(route('documents.create.drafts'));

        $draft = Document::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                ...$payload,
                'draft_id' => $draft->id,
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertSame(
            1,
            $draft->files()->where('type_file', 'attachment')->count(),
        );
    }

    private function initialResubmissionFixture(): array
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([
            ApprovalStatus::PENDING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::TERMINATED => 'Dihentikan',
        ] as $code => $name) {
            ApprovalStatus::query()->firstOrCreate([
                'kode_status' => $code,
            ], [
                'nama_status' => $name,
            ]);
        }

        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        return [$user, $businessProcess, $businessFunction, $department, $level, $documentType];
    }

    private function initialSubmitPayload(BusinessProcess $businessProcess, BusinessFunction $businessFunction, Department $department, string $suffix, array $overrides = []): array
    {
        return array_merge([
            'nama_dokumen' => 'Prosedur Resubmit',
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'department_ids' => [$department->id],
            'official_preparer_id' => User::query()->firstOrFail()->id,
            'nomor_dokumen_suffix' => $suffix,
            'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
            'submit_action' => 'submit',
        ], $overrides);
    }

    private function createRejectedInitialAttempt(
        User $user,
        DocumentLevel $level,
        DocumentType $documentType,
        BusinessProcess $businessProcess,
        BusinessFunction $businessFunction,
        string $documentNumber,
        array $overrides = [],
        string $note = 'Dokumen belum sesuai.',
    ): Document {
        $document = Document::create(array_merge([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Ditolak',
            'nomor_dokumen' => $documentNumber,
            'rejected_at' => now(),
        ], $overrides));

        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::REJECTED)->id,
            'user_id' => User::factory()->create()->id,
            'role_id' => null,
            'assigned_by' => $user->id,
            'assigned_at' => now()->subMinute(),
            'responded_at' => now(),
            'stages' => 'Approval Dokumen',
            'catatan' => $note,
        ]);

        return $document;
    }

    private function revisionCreationFixture(): array
    {
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $submitter = User::factory()->create(['m_department_id' => $department->id]);
        $officialPreparer = User::factory()->create();
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::REJECTED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::CANCELLED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        ApprovalStatus::query()->firstOrCreate([
            'kode_status' => ApprovalStatus::APPROVED,
        ], [
            'nama_status' => 'Disetujui',
        ]);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Form']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Prosedur Revisi Master',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        return [$source, $submitter, $officialPreparer];
    }

    private function revisionSubmitPayload(Document $source, User $officialPreparer, array $overrides = []): array
    {
        return array_merge([
            'revised_from' => $source->id,
            'nama_dokumen' => 'Prosedur Revisi Baru',
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'department_ids' => $source->departments()->pluck('departments.id')->all(),
            'official_preparer_id' => $officialPreparer->id,
            'nomor_dokumen_suffix' => '999',
            'revision_content' => UploadedFile::fake()->create('dokumen-revisi.pdf', 24, 'application/pdf'),
            'revision_form' => UploadedFile::fake()->create('lembar-revisi.pdf', 24, 'application/pdf'),
            'submit_action' => 'submit',
        ], $overrides);
    }
}
