<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
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
            ->assertSee('Level Dokumen:')
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
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => ApprovalStatus::PENDING,
        ]);
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

    public function test_submitted_document_creates_pending_approval(): void
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
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => ApprovalStatus::PENDING,
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);

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

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->whereHas('status', fn ($query) => $query->where('kode_status', 'PENDING'))
            ->exists());
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
        DocumentType::create(['nama_types' => 'IK']);

        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id, $secondDepartment->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Instruksi Kerja Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
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
                'department_ids',
                'official_preparer_id',
                'nomor_dokumen_suffix',
                'filled_template',
            ]);
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
