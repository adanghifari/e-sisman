<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
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
                'filled_template' => UploadedFile::fake()->create('template.docx', 24),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Instruksi Kerja Pengujian', $document->nama_dokumen);
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

    public function test_template_upload_must_be_word_document(): void
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
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['filled_template']);
    }

    public function test_attachments_must_be_pdf_or_word_documents(): void
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
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.xlsx', 24),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['attachments.0']);
    }
}
