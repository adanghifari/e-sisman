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

class DocumentInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_document_is_shown_in_needs_process_tab(): void
    {
        $user = User::factory()->create(['name' => 'Pengaju Dokumen']);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $document = $this->createDocument($user, StatusDocument::PROPOSED, [
            'nama_dokumen' => 'Prosedur Kalibrasi Alat',
            'nomor_dokumen' => 'PS-SMR-123',
            'submitted_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Prosedur Kalibrasi Alat')
            ->assertSee('PS-SMR-123')
            ->assertSee('Diajukan')
            ->assertSee('Pengaju Dokumen')
            ->assertSee('QA');
    }

    public function test_draft_document_is_not_shown_in_needs_process_tab(): void
    {
        $user = User::factory()->create();
        $this->createDocument($user, StatusDocument::DRAFT, [
            'nama_dokumen' => 'Draft Belum Diajukan',
            'nomor_dokumen' => 'PS-SMR-999',
            'submitted_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Draft Belum Diajukan')
            ->assertDontSee('PS-SMR-999');
    }

    public function test_level_two_submission_appears_in_needs_process_tab(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['name' => 'Submitter User']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operasional',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'Prosedur']);

        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Prosedur Submit Inbox',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '456',
                'filled_template' => UploadedFile::fake()->create('template.docx', 24),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'))
            ->assertSessionHas('document_success');

        $this->get(route('documents.create'))
            ->assertOk()
            ->assertSee('Dokumen berhasil disubmit')
            ->assertSee('Dokumen akan segera diproses oleh tim terkait.');

        $this->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Prosedur Submit Inbox')
            ->assertSee('PS-SMR-456')
            ->assertSee('Diajukan')
            ->assertSee('Submitter User')
            ->assertSee('OPS');
    }

    private function createDocument(User $user, string $statusName, array $attributes = []): Document
    {
        $status = StatusDocument::create(['nama_status' => $statusName]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        return Document::create($attributes + [
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Dokumen Pengujian',
            'nomor_dokumen' => 'PS-SMR-001',
            'submitted_at' => now(),
        ]);
    }
}
