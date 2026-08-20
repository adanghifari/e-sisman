<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\ApprovalStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateDocumentTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_level_two_create_page_uses_integrated_create_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Level Two User',
            'email' => 'level-two@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('Tambah Dokumen Level II')
            ->assertSee('Nama Dokumen')
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
            ->assertRedirect(route('documents.create.level', 'level-1'));

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
            ->assertRedirect(route('documents.create.level', 'level-2'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Prosedur Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertSame('PS-SMR-002', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
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
            ->assertSee('Ajukan Revisi Dokumen Level IV')
            ->assertSee('FMPS')
            ->assertSee('PS-SMR-010')
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
                'filled_template' => UploadedFile::fake()->create('template-revisi.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame('FMPS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('level-4', $revision->documentLevel->kode);
        $this->assertSame('Form', $revision->documentType->nama_types);
        $this->assertSame(1, $revision->nomor_revisi);
        $this->assertSame($businessProcess->id, $revision->m_proses_bisnis_id);
        $this->assertSame($businessFunction->id, $revision->m_proses_fungsi_id);
        $this->assertTrue($revision->departments()->whereKey($sourceDepartment->id)->exists());
        $this->assertFalse($revision->departments()->whereKey($otherDepartment->id)->exists());

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Master Updated')
            ->assertSee('Pengajuan Revisi')
            ->assertSee(StatusDocument::PROPOSED);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Kedua',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'filled_template' => UploadedFile::fake()->create('template-revisi-2.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $secondRevision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Kedua')
            ->firstOrFail();

        $this->assertSame($source->id, $secondRevision->revised_from);
        $this->assertSame('FMPS-SMR-010', $secondRevision->nomor_dokumen);
        $this->assertSame(2, $secondRevision->nomor_revisi);
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
            ->assertRedirect(route('documents.create.level', 'level-3'));

        $document = Document::query()
            ->where('nama_dokumen', 'Instruksi Kerja Pengujian')
            ->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Instruksi Kerja Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertSame($procedure->id, $document->reference);
        $this->assertSame('IK-XXX-YY-001', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertTrue($document->departments()->whereKey($secondDepartment->id)->exists());
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'submit_action' => 'draft',
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
                'submit_action' => 'draft',
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
            'nomor_dokumen' => 'PS-SMR-001',
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
}
