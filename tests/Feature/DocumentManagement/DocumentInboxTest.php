<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_approval_for_login_user_is_shown_in_needs_process_tab(): void
    {
        $approver = User::factory()->create(['name' => 'Approver Login']);
        $submitter = User::factory()->create(['name' => 'Pengaju Dokumen']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Prosedur Kalibrasi Alat',
            'nomor_dokumen' => 'PS-SMR-123',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => 'Approval Manager',
            'assigned_at' => now(),
        ]);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Perlu Saya Proses')
            ->assertSee('Riwayat yang Saya Proses')
            ->assertSee('Prosedur Kalibrasi Alat')
            ->assertSee('PS-SMR-123')
            ->assertSee('Approval Manager')
            ->assertSee('PENDING')
            ->assertSee('Pengaju Dokumen');
    }

    public function test_pending_approval_for_other_user_is_not_shown_in_needs_process_tab(): void
    {
        $loginUser = User::factory()->create();
        $otherApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Approver Lain',
            'nomor_dokumen' => 'PS-SMR-456',
        ]);
        $this->createApproval($document, $otherApprover, ApprovalStatus::PENDING);

        $this->actingAs($loginUser)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Approver Lain')
            ->assertDontSee('PS-SMR-456');
    }

    public function test_developer_can_see_pending_approvals_for_all_users(): void
    {
        $developer = User::factory()->create([
            'nik' => '000000',
            'name' => 'Developer',
            'email' => 'developer@example.com',
        ]);
        $otherApprover = User::factory()->create(['name' => 'Approver Lain']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Terlihat Developer',
            'nomor_dokumen' => 'PS-SMR-DEV',
        ]);
        $this->createApproval($document, $otherApprover, ApprovalStatus::PENDING);

        $this->actingAs($developer)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Terlihat Developer')
            ->assertSee('PS-SMR-DEV');
    }

    public function test_responded_approval_for_login_user_is_shown_in_processed_history_tab(): void
    {
        $approver = User::factory()->create(['name' => 'Approver Login']);
        $submitter = User::factory()->create(['name' => 'Pengaju Riwayat']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Instruksi Kerja Disetujui',
            'nomor_dokumen' => 'IK-SMR-789',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::APPROVED, [
            'stages' => 'Review Kadis',
            'responded_at' => now(),
        ]);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Riwayat yang Saya Proses')
            ->assertSee('Instruksi Kerja Disetujui')
            ->assertSee('IK-SMR-789')
            ->assertSee('Review Kadis')
            ->assertSee('APPROVED')
            ->assertSee('Pengaju Riwayat');
    }

    public function test_developer_can_see_processed_history_for_all_users(): void
    {
        $developer = User::factory()->create([
            'nik' => '000000',
            'name' => 'Developer',
            'email' => 'developer@example.com',
        ]);
        $otherApprover = User::factory()->create(['name' => 'Approver Riwayat Lain']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Riwayat Terlihat Developer',
            'nomor_dokumen' => 'IK-SMR-DEV',
        ]);
        $this->createApproval($document, $otherApprover, ApprovalStatus::APPROVED, [
            'responded_at' => now(),
        ]);

        $this->actingAs($developer)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Riwayat Terlihat Developer')
            ->assertSee('IK-SMR-DEV');
    }

    public function test_approval_detail_page_shows_readonly_document_and_actions(): void
    {
        Storage::fake('local');

        $approver = User::factory()->create(['name' => 'Approver Detail']);
        $submitter = User::factory()->create(['name' => 'Pengaju Detail']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Detail Approval',
            'nomor_dokumen' => 'PS-SMR-DETAIL',
        ]);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => 'Review Detail',
        ]);

        Storage::disk('local')->put("documents/{$document->id}/isi.pdf", '%PDF-1.4');
        DocumentFile::create([
            't_document_id' => $document->id,
            'type_file' => 'filled_template',
            'path_file' => "documents/{$document->id}/isi.pdf",
            'uploaded_by' => $submitter->id,
            'updated_at' => now(),
            'original_file_name' => 'isi.pdf',
            'stored_file_name' => 'isi.pdf',
            'file_size' => 24,
        ]);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee(route('documents.approval.show', $document));

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Detail Dokumen Level II')
            ->assertSee('Dokumen Detail Approval')
            ->assertSee('PS-SMR-DETAIL')
            ->assertSee('Isi Dokumen')
            ->assertSee('Lampiran')
            ->assertSee('Approve')
            ->assertSee('Tolak')
            ->assertSee('Assign Approver')
            ->assertSee('Approval Flow Dokumen Level II : Prosedur SKMBS')
            ->assertSee('Diperiksa oleh')
            ->assertSee('Manager')
            ->assertSee('Tambah Approver')
            ->assertSee('Save Approver');

        $nextApprover = User::factory()->create(['name' => 'Next Approver']);
        $secondApprover = User::factory()->create(['name' => 'Second Approver']);

        $this->actingAs($approver)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$nextApprover->id, $secondApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $nextApprover->id)
            ->where('stages', 'Diperiksa oleh Manager')
            ->exists());
        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $secondApprover->id)
            ->where('stages', 'Diperiksa oleh Manager')
            ->exists());

        $this->actingAs($approver)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$secondApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $nextApprover->id)
            ->where('stages', 'Diperiksa oleh Manager')
            ->exists());
        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $secondApprover->id)
            ->where('stages', 'Diperiksa oleh Manager')
            ->exists());
    }

    public function test_assign_approver_requires_each_flow_stage_to_have_approver(): void
    {
        $approver = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $firstStage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);
        $secondStage = $flow->stages()->create([
            'stage_order' => 2,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        $this->actingAs($approver)
            ->from(route('documents.approval.show', $document))
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [$approver->id],
                    $secondStage->id => [],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$secondStage->id}"]);
    }

    public function test_document_becomes_master_after_all_flow_stage_approvals_are_approved(): void
    {
        $firstApprover = User::factory()->create(['name' => 'Approver Tahap Satu']);
        $secondApprover = User::factory()->create(['name' => 'Approver Tahap Dua']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Jadi Master',
            'nomor_dokumen' => 'PS-SMR-MASTER',
        ]);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);
        $flow->stages()->create([
            'stage_order' => 2,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => ApprovalStatus::APPROVED,
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::REJECTED,
            'nama_status' => ApprovalStatus::REJECTED,
        ]);

        $this->createApproval($document, $firstApprover, ApprovalStatus::PENDING, [
            'stages' => 'Dibuat oleh Staff',
        ]);
        $this->createApproval($document, $secondApprover, ApprovalStatus::PENDING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($firstApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(
            StatusDocument::PROPOSED,
            $document->refresh()->status->nama_status,
        );

        $this->actingAs($secondApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(
            StatusDocument::APPROVED,
            $document->refresh()->status->nama_status,
        );
        $this->assertNotNull($document->approved_at);

        $this->actingAs($submitter)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Dokumen Jadi Master')
            ->assertSee('PS-SMR-MASTER');
    }

    public function test_first_flow_stage_defaults_to_official_preparer_without_saving_assignment(): void
    {
        $approver = User::factory()->create();
        $officialPreparer = User::factory()->create(['name' => 'Penyusun Resmi Default']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'official_preparer_id' => $officialPreparer->id,
        ]);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Penyusun Resmi Default');

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'Dibuat oleh Staff')
            ->exists());
    }

    public function test_pdf_preview_is_served_without_conversion(): void
    {
        Storage::fake('local');

        $approver = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        Storage::disk('local')->put("documents/{$document->id}/isi.pdf", '%PDF-1.4');
        $file = DocumentFile::create([
            't_document_id' => $document->id,
            'type_file' => 'filled_template',
            'path_file' => "documents/{$document->id}/isi.pdf",
            'uploaded_by' => $submitter->id,
            'updated_at' => now(),
            'original_file_name' => 'isi.pdf',
            'stored_file_name' => 'isi.pdf',
            'file_size' => 24,
        ]);

        $this->actingAs($approver)
            ->get(route('documents.approval.files.preview', [$document, $file]))
            ->assertOk();
    }

    private function createDocument(User $user, array $attributes = []): Document
    {
        $status = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $document = Document::create($attributes + [
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
        $document->departments()->sync([$department->id]);

        return $document;
    }

    private function createApproval(Document $document, User $approver, string $statusCode, array $attributes = []): Approval
    {
        $status = ApprovalStatus::query()->firstOrCreate(
            ['kode_status' => $statusCode],
            ['nama_status' => $statusCode],
        );
        $role = Role::create(['nama_role' => fake()->unique()->word()]);

        return Approval::create($attributes + [
            't_document_id' => $document->id,
            'm_approval_status_id' => $status->id,
            'user_id' => $approver->id,
            'role_id' => $role->id,
            'assigned_by' => $document->user_id,
            'assigned_at' => now(),
            'stages' => 'Approval',
        ]);
    }
}
