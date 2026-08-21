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
use App\Models\Permission;
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
        $assignedAt = now()->setDate(2026, 8, 18)->setTime(14, 25, 36);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Prosedur Kalibrasi Alat',
            'nomor_dokumen' => 'PS-SMR-123',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => 'Approval Manager',
            'assigned_at' => $assignedAt,
        ]);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Perlu Saya Proses')
            ->assertSee('Riwayat yang Saya Proses')
            ->assertSee('Prosedur Kalibrasi Alat')
            ->assertSee('PS-SMR-123')
            ->assertSee('Approval Manager')
            ->assertSee('18 Aug 2026 14:25:36')
            ->assertSee('Menunggu Manager')
            ->assertSee('Dalam Review')
            ->assertSee('Pengaju Dokumen');
    }

    public function test_work_instruction_type_is_displayed_with_full_label(): void
    {
        $approver = User::factory()->create();
        $submitter = User::factory()->create();
        $documentType = DocumentType::create(['nama_types' => 'IK']);
        $document = $this->createDocument($submitter, [
            'm_document_types_id' => $documentType->id,
            'nama_dokumen' => 'Instruksi Kerja Incoming',
            'nomor_dokumen' => 'IK-SMR-123',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Instruksi Kerja')
            ->assertDontSee('>IK</td>', false);
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

    public function test_rejected_document_is_shown_in_submitter_needs_process_tab_for_correction(): void
    {
        $submitter = User::factory()->create(['name' => 'Pengaju Rejected']);
        $rejectedStatus = StatusDocument::create(['nama_status' => StatusDocument::REJECTED]);
        $document = $this->createDocument($submitter, [
            'm_status_document_id' => $rejectedStatus->id,
            'nama_dokumen' => 'Dokumen Perlu Diperbaiki',
            'nomor_dokumen' => 'PS-SMR-REJECTED',
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Perlu Diperbaiki')
            ->assertSee('PS-SMR-REJECTED')
            ->assertSee('Perbaikan Pengajuan')
            ->assertSee('Perlu Perbaikan')
            ->assertSee(StatusDocument::REJECTED);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertDontSee('Dokumen Perlu Diperbaiki')
            ->assertDontSee('PS-SMR-REJECTED');
    }

    public function test_rejected_document_detail_shows_correction_button_for_related_user(): void
    {
        $submitter = User::factory()->create();
        $rejectedStatus = StatusDocument::create(['nama_status' => StatusDocument::REJECTED]);
        $document = $this->createDocument($submitter, [
            'm_status_document_id' => $rejectedStatus->id,
            'nama_dokumen' => 'Detail Rejected Bisa Diperbaiki',
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Detail Rejected Bisa Diperbaiki')
            ->assertSee('Perbaiki Pengajuan')
            ->assertSee('Batal');
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

    public function test_developer_can_see_proposed_documents_without_assigned_approver(): void
    {
        $developer = User::factory()->create([
            'nik' => '000000',
            'name' => 'Developer',
            'email' => 'developer@example.com',
        ]);
        $submitter = User::factory()->create(['name' => 'Pengaju Clean']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Belum Assign Approver',
            'nomor_dokumen' => 'PS-SMR-CLEAN',
        ]);

        $this->actingAs($developer)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Belum Assign Approver')
            ->assertSee('PS-SMR-CLEAN')
            ->assertSee('Belum assign approver')
            ->assertSee('Perlu Verifikasi Admin KD')
            ->assertSee(route('documents.approval.show', $document));

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Belum Assign Approver')
            ->assertDontSee('PS-SMR-CLEAN');
    }

    public function test_developer_can_manage_approver_assignment_without_document_control_role(): void
    {
        $this->ensureApprovalStatuses();

        $developer = User::factory()->create([
            'nik' => '000000',
            'name' => 'Developer',
            'email' => 'developer@example.com',
        ]);
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Developer Assign',
            'nomor_dokumen' => 'PS-SMR-DEV-ASSIGN',
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

        $this->actingAs($developer)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Tambah Approver')
            ->assertSee('Save Approver');

        $this->actingAs($developer)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$approver->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $approver->id)
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
            ->exists());
    }

    public function test_document_control_admin_from_related_department_can_see_proposed_document_to_assign(): void
    {
        $submitter = User::factory()->create(['name' => 'Pengaju Department']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Assign Department Terkait',
            'nomor_dokumen' => 'PS-SMR-ASSIGN',
        ]);
        $admin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->actingAs($admin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Assign Department Terkait')
            ->assertSee('PS-SMR-ASSIGN')
            ->assertSee('Belum assign approver')
            ->assertSee('Perlu Verifikasi Admin KD')
            ->assertSee(route('documents.approval.show', $document));
    }

    public function test_document_control_admin_with_assign_permission_can_see_proposed_document_from_unrelated_department(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Assign Department Lain',
            'nomor_dokumen' => 'PS-SMR-OTHER-DEPT',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $admin = $this->documentControlAdmin($otherDepartment);

        $this->actingAs($admin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Assign Department Lain')
            ->assertSee('PS-SMR-OTHER-DEPT')
            ->assertSee('Belum assign approver')
            ->assertSee('Perlu Verifikasi Admin KD')
            ->assertSee(route('documents.approval.show', $document));
    }

    public function test_regular_user_from_related_department_cannot_see_proposed_document_to_assign(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Assign Bukan Admin',
            'nomor_dokumen' => 'PS-SMR-NON-ADMIN',
        ]);
        $user = User::factory()->create([
            'm_department_id' => $document->departments()->firstOrFail()->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Assign Bukan Admin')
            ->assertDontSee('PS-SMR-NON-ADMIN');
    }

    public function test_document_control_admin_can_see_multi_department_document_when_one_department_matches(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Multi Department Assign',
            'nomor_dokumen' => 'PS-SMR-MULTI',
        ]);
        $hr = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $document->departments()->syncWithoutDetaching([$hr->id]);
        $admin = $this->documentControlAdmin($hr);

        $this->actingAs($admin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Multi Department Assign')
            ->assertSee('PS-SMR-MULTI')
            ->assertSee('Perlu Verifikasi Admin KD');
    }

    public function test_document_control_admin_from_related_department_can_open_proposed_document_detail(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Detail Assign Department Terkait',
            'nomor_dokumen' => 'PS-SMR-DETAIL-ASSIGN',
        ]);
        $admin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->actingAs($admin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Detail Assign Department Terkait')
            ->assertSee('Assign Approver');
    }

    public function test_document_control_admin_with_assign_permission_can_open_proposed_document_from_unrelated_department(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Detail Assign Department Lain',
            'nomor_dokumen' => 'PS-SMR-DETAIL-OTHER',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $admin = $this->documentControlAdmin($otherDepartment);

        $this->actingAs($admin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Detail Assign Department Lain')
            ->assertSee('Assign Approver');
    }

    public function test_regular_user_from_related_department_cannot_open_unassigned_document_detail(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Detail Assign Bukan Admin',
            'nomor_dokumen' => 'PS-SMR-DETAIL-NON-ADMIN',
        ]);
        $user = User::factory()->create([
            'm_department_id' => $document->departments()->firstOrFail()->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.approval.show', $document))
            ->assertForbidden();
    }

    public function test_future_waiting_approver_cannot_open_document_detail_before_stage_is_active(): void
    {
        $waitingApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Future Stage',
            'nomor_dokumen' => 'PS-SMR-WAITING',
        ]);
        $this->createApproval($document, $waitingApprover, ApprovalStatus::WAITING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($waitingApprover)
            ->get(route('documents.approval.show', $document))
            ->assertForbidden();
    }

    public function test_waiting_approval_is_not_shown_in_needs_process_tab(): void
    {
        $waitingApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Menunggu Tahap Berikutnya',
            'nomor_dokumen' => 'PS-SMR-WAITING-INBOX',
        ]);
        $this->createApproval($document, $waitingApprover, ApprovalStatus::WAITING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($waitingApprover)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Menunggu Tahap Berikutnya')
            ->assertDontSee('PS-SMR-WAITING-INBOX');
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
            ->assertSee('Disetujui')
            ->assertSee('Pengaju Riwayat');
    }

    public function test_submitter_sees_pending_revision_in_needs_process_when_only_official_signature_exists(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create(['name' => 'Pengaju Revisi']);
        $parent = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Parent',
            'nomor_dokumen' => 'IK-SMR-PARENT',
        ]);
        $document = Document::create([
            'm_document_level_id' => $parent->m_document_level_id,
            'm_status_document_id' => $parent->m_status_document_id,
            'm_document_types_id' => $parent->m_document_types_id,
            'm_proses_bisnis_id' => $parent->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $parent->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Revisi Baru',
            'nomor_dokumen' => 'FMIK-SMR-PARENT',
            'revised_from' => $parent->id,
            'submitted_at' => now(),
        ]);
        $document->departments()->sync($parent->departments()->pluck('departments.id')->all());

        $this->createApproval($document, $submitter, ApprovalStatus::APPROVED, [
            'stages' => 'TTD Penyusun Resmi',
            'responded_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Revisi Baru')
            ->assertDontSee('TTD Penyusun Resmi');

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Dokumen Revisi Baru')
            ->assertSee('Pengajuan Revisi')
            ->assertSee('FMIK-SMR-PARENT')
            ->assertSee(StatusDocument::PROPOSED);
    }

    public function test_submitter_history_shows_revision_form_number_after_work_instruction_revision_is_approved(): void
    {
        $submitter = User::factory()->create(['name' => 'Pengaju Revisi IK']);
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $workInstructionLevel = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $workInstructionType = DocumentType::query()->firstOrCreate(['nama_types' => 'IK']);
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
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

        $source = Document::create([
            'm_document_level_id' => $workInstructionLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $workInstructionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Instruksi Kerja Lama',
            'nomor_dokumen' => 'IK-MRI-01-04',
            'nomor_revisi' => 0,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
        ]);
        $source->departments()->sync([$department->id]);

        $revision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Instruksi Kerja Revisi',
            'nomor_dokumen' => 'IK-MRI-01-04',
            'nomor_revisi' => 1,
            'submitted_at' => now()->subHour(),
            'approved_at' => now(),
        ]);
        $revision->departments()->sync([$department->id]);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Instruksi Kerja Revisi')
            ->assertSee('FMIK-MRI-01-04');
    }

    public function test_responded_approver_can_open_document_detail_from_processed_history(): void
    {
        $approver = User::factory()->create(['name' => 'Approver Detail Riwayat']);
        $submitter = User::factory()->create();
        $respondedAt = now()->setDate(2026, 8, 18)->setTime(14, 25, 36);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Detail Riwayat Approval',
            'nomor_dokumen' => 'IK-SMR-HISTORY',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::APPROVED, [
            'stages' => 'Review Kadis',
            'responded_at' => $respondedAt,
        ]);

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Detail Riwayat Approval')
            ->assertSee('Diproses pada 18 Aug 2026 14:25:36')
            ->assertDontSee('Keputusan Approval');
    }

    public function test_revision_detail_from_processed_history_shows_master_and_revision_numbers(): void
    {
        $approver = User::factory()->create(['name' => 'Approver Revisi Detail']);
        $submitter = User::factory()->create(['name' => 'Pengaju Revisi Detail']);
        $source = $this->createDocument($submitter, [
            'nama_dokumen' => 'Prosedur Lama Detail',
            'nomor_dokumen' => 'PS-SMR-OLD',
            'nomor_revisi' => 0,
        ]);
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $source->update(['m_status_document_id' => $approvedStatus->id]);
        $revision = Document::create([
            'm_document_level_id' => $source->m_document_level_id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $source->m_document_types_id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Prosedur Revisi Detail',
            'nomor_dokumen' => 'FMPS-SMR-OLD',
            'nomor_revisi' => 1,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'submitted_at' => now(),
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());
        $this->createApproval($revision, $approver, ApprovalStatus::APPROVED, [
            'responded_at' => now(),
        ]);

        $this->actingAs($approver)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Detail')
            ->assertSee('FMPS-SMR-OLD');

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('Nomor Dokumen')
            ->assertSee('PS-SMR-OLD')
            ->assertSee('Nomor Dokumen Revisi')
            ->assertSee('FMPS-SMR-OLD');
    }

    public function test_developer_does_not_see_processed_history_for_other_users_without_processing_it(): void
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
            ->assertDontSee('Riwayat Terlihat Developer')
            ->assertDontSee('IK-SMR-DEV');
    }

    public function test_document_control_admin_does_not_see_in_progress_assigned_document_in_processed_history(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create(['name' => 'Pengaju Assign']);
        $approver = User::factory()->create(['name' => 'Approver Assign']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Selesai Assign',
            'nomor_dokumen' => 'PS-SMR-ASSIGN-HISTORY',
        ]);
        $admin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $assignedAt = now()->setDate(2026, 8, 20)->setTime(10, 15);

        $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'assigned_by' => $admin->id,
            'assigned_at' => $assignedAt,
            'stages' => 'Superintendent',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertDontSee('Dokumen Selesai Assign')
            ->assertDontSee('PS-SMR-ASSIGN-HISTORY');
    }

    public function test_assigned_document_stays_in_document_control_admin_needs_process_tab_as_monitoring_task(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create(['name' => 'Pengaju Assigned']);
        $approver = User::factory()->create(['name' => 'Approver Assigned']);
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Sudah Assign',
            'nomor_dokumen' => 'PS-SMR-ASSIGNED',
        ]);
        $admin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'assigned_by' => $admin->id,
            'stages' => 'Superintendent',
        ]);

        $this->actingAs($admin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Dokumen Sudah Assign')
            ->assertSee('PS-SMR-ASSIGNED')
            ->assertSee('Superintendent')
            ->assertSee('Menunggu Superintendent');
    }

    public function test_approval_detail_page_shows_readonly_document_and_actions(): void
    {
        $this->ensureApprovalStatuses();

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
            ->assertDontSee('Assign Approver')
            ->assertDontSee('Approval Flow Dokumen Level II : Prosedur SKMBS')
            ->assertDontSee('Assignment approver dikelola oleh Admin Kontrol Dokumen department terkait.')
            ->assertDontSee('Tambah Approver')
            ->assertDontSee('Save Approver');

        $nextApprover = User::factory()->create(['name' => 'Next Approver']);
        $secondApprover = User::factory()->create(['name' => 'Second Approver']);

        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->actingAs($documentControlAdmin)
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

        $this->actingAs($documentControlAdmin)
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

    public function test_regular_approver_cannot_assign_document_approvers(): void
    {
        $this->ensureApprovalStatuses();

        $approver = User::factory()->create();
        $nextApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        $this->actingAs($approver)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$nextApprover->id],
                ],
            ])
            ->assertForbidden();

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $nextApprover->id)
            ->exists());
    }

    public function test_document_control_admin_can_see_assign_approver_controls(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Assign Approver')
            ->assertSee('Dibuat oleh')
            ->assertSee('Tambah Approver')
            ->assertSee('Save Approver')
            ->assertSee('action="'.route('documents.approval.assign', $document).'"', false)
            ->assertDontSee('action="'.url("documents/inbox/{$document->id}/assign").'"', false);
    }

    public function test_assign_approver_requires_each_flow_stage_to_have_approver(): void
    {
        $this->ensureApprovalStatuses();

        $approver = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
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

        $this->actingAs($documentControlAdmin)
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

    public function test_assign_approver_validation_redirects_to_document_detail_without_referer(): void
    {
        $this->ensureApprovalStatuses();

        $approver = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
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

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [$approver->id],
                    $secondStage->id => [],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$secondStage->id}"]);
    }

    public function test_opening_assign_endpoint_with_get_redirects_to_document_detail(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->actingAs($documentControlAdmin)
            ->get(url("documents/inbox/{$document->id}/assign"))
            ->assertRedirect(route('documents.approval.show', $document));
    }

    public function test_posting_to_legacy_assign_endpoint_redirects_to_document_detail(): void
    {
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->actingAs($documentControlAdmin)
            ->post(url("documents/inbox/{$document->id}/assign"))
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(['stage_approvers']);
    }

    public function test_assign_approver_sets_first_stage_pending_and_later_stages_waiting(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create();
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
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

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [$firstApprover->id],
                    $secondStage->id => [$secondApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(
            ApprovalStatus::PENDING,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $firstApprover->id)
                ->firstOrFail()
                ->status
                ->kode_status,
        );
        $this->assertSame(
            ApprovalStatus::WAITING,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $secondApprover->id)
                ->firstOrFail()
                ->status
                ->kode_status,
        );
    }

    public function test_approved_approver_cannot_be_removed_from_assignment(): void
    {
        $this->ensureApprovalStatuses();

        $approvedApprover = User::factory()->create();
        $pendingApprover = User::factory()->create();
        $replacementApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $approvedApprover, ApprovalStatus::APPROVED, [
            'stages' => 'Diperiksa oleh Manager',
            'responded_at' => now(),
        ]);
        $this->createApproval($document, $pendingApprover, ApprovalStatus::PENDING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($documentControlAdmin)
            ->from(route('documents.approval.show', $document))
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$pendingApprover->id, $replacementApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$stage->id}"]);

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $approvedApprover->id)
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::APPROVED))
            ->exists());
        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $replacementApprover->id)
            ->exists());
    }

    public function test_responded_approver_cannot_be_removed_from_assignment(): void
    {
        $this->ensureApprovalStatuses();

        $respondedApprover = User::factory()->create();
        $pendingApprover = User::factory()->create();
        $replacementApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $respondedApprover, ApprovalStatus::REJECTED, [
            'stages' => 'Diperiksa oleh Manager',
            'responded_at' => now(),
        ]);
        $this->createApproval($document, $pendingApprover, ApprovalStatus::PENDING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($documentControlAdmin)
            ->from(route('documents.approval.show', $document))
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$pendingApprover->id, $replacementApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$stage->id}"]);

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $respondedApprover->id)
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::REJECTED))
            ->exists());
        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $replacementApprover->id)
            ->exists());
    }

    public function test_fully_approved_stage_assignment_cannot_be_changed(): void
    {
        $this->ensureApprovalStatuses();

        $approvedApprover = User::factory()->create();
        $replacementApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $this->createApproval($document, $approvedApprover, ApprovalStatus::APPROVED, [
            'stages' => 'Diperiksa oleh Manager',
            'responded_at' => now(),
        ]);

        $this->actingAs($documentControlAdmin)
            ->from(route('documents.approval.show', $document))
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$approvedApprover->id, $replacementApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$stage->id}"]);

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $replacementApprover->id)
            ->exists());
    }

    public function test_approved_or_rejected_document_assignment_is_locked(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create();
        $newApprover = User::factory()->create();
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $document = $this->createDocument($submitter, [
            'm_status_document_id' => $approvedStatus->id,
        ]);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$newApprover->id],
                ],
            ])
            ->assertForbidden();

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $newApprover->id)
            ->exists());
    }

    public function test_next_stage_is_activated_after_current_stage_is_fully_approved(): void
    {
        $this->ensureApprovalStatuses();

        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $nextStageApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
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
        $this->createApproval($document, $firstApprover, ApprovalStatus::PENDING, [
            'stages' => 'Dibuat oleh Staff',
        ]);
        $this->createApproval($document, $secondApprover, ApprovalStatus::PENDING, [
            'stages' => 'Dibuat oleh Staff',
        ]);
        $this->createApproval($document, $nextStageApprover, ApprovalStatus::WAITING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);

        $this->actingAs($firstApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHas('document_success.title', 'Dokumen Berhasil Disetujui');

        $this->assertSame(
            ApprovalStatus::WAITING,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $nextStageApprover->id)
                ->firstOrFail()
                ->status
                ->kode_status,
        );

        $this->actingAs($secondApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(
            ApprovalStatus::PENDING,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $nextStageApprover->id)
                ->firstOrFail()
                ->status
                ->kode_status,
        );
    }

    public function test_assigned_approver_can_approve_even_when_static_approve_permission_is_not_granted(): void
    {
        $this->ensureApprovalStatuses();
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);

        Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.approve'],
            [
                'name' => 'Approve Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.approve',
                'action' => 'approve',
            ],
        );
        $userRole = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $approver = User::factory()->create();
        $approver->roles()->syncWithoutDetaching([$userRole->id]);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'nama_dokumen' => 'Dokumen Static Permission Lama',
            'nomor_dokumen' => 'PS-SMR-STALE-PERM',
        ]);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING);

        $this->assertFalse($approver->fresh()->hasExplicitPermission('documents.approval.approve'));

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHas('document_success.title', 'Dokumen Berhasil Disetujui');

        $this->assertSame(
            ApprovalStatus::APPROVED,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $approver->id)
                ->firstOrFail()
                ->status
                ->kode_status,
        );
    }

    public function test_reject_terminates_other_pending_and_waiting_approvals(): void
    {
        $this->ensureApprovalStatuses();

        $rejectingApprover = User::factory()->create();
        $otherPendingApprover = User::factory()->create();
        $waitingApprover = User::factory()->create();
        $approvedApprover = User::factory()->create();
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::REJECTED]);
        $this->createApproval($document, $rejectingApprover, ApprovalStatus::PENDING, [
            'stages' => 'Dibuat oleh Staff',
        ]);
        $this->createApproval($document, $otherPendingApprover, ApprovalStatus::PENDING, [
            'stages' => 'Dibuat oleh Staff',
        ]);
        $this->createApproval($document, $waitingApprover, ApprovalStatus::WAITING, [
            'stages' => 'Diperiksa oleh Manager',
        ]);
        $this->createApproval($document, $approvedApprover, ApprovalStatus::APPROVED, [
            'stages' => 'Dibuat oleh Staff',
            'responded_at' => now(),
        ]);

        $this->actingAs($rejectingApprover)
            ->post(route('documents.approval.reject', $document), [
                'catatan' => 'Dokumen belum sesuai.',
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHas('document_success.title', 'Dokumen Berhasil Ditolak');

        $this->assertSame(StatusDocument::REJECTED, $document->refresh()->status->nama_status);
        $this->assertSame(
            ApprovalStatus::REJECTED,
            Approval::query()->where('user_id', $rejectingApprover->id)->firstOrFail()->status->kode_status,
        );
        $this->assertSame(
            ApprovalStatus::TERMINATED,
            Approval::query()->where('user_id', $otherPendingApprover->id)->firstOrFail()->status->kode_status,
        );
        $this->assertSame(
            ApprovalStatus::TERMINATED,
            Approval::query()->where('user_id', $waitingApprover->id)->firstOrFail()->status->kode_status,
        );
        $this->assertSame(
            ApprovalStatus::APPROVED,
            Approval::query()->where('user_id', $approvedApprover->id)->firstOrFail()->status->kode_status,
        );
    }

    public function test_document_becomes_master_after_all_flow_stage_approvals_are_approved(): void
    {
        $this->ensureApprovalStatuses();

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

    public function test_approved_revision_obsoletes_previous_master_document(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $source = $this->createDocument($submitter, [
            'nama_dokumen' => 'Instruksi Lama',
            'nomor_dokumen' => 'IK-SMR-OLD',
        ]);
        $approvedDocumentStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $source->update([
            'm_status_document_id' => $approvedDocumentStatus->id,
            'approved_at' => now()->subDay(),
        ]);

        $revision = Document::create([
            'm_document_level_id' => $source->m_document_level_id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $source->m_document_types_id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'revised_from' => $source->id,
            'nama_dokumen' => 'Instruksi Revisi',
            'nomor_dokumen' => 'FMIK-SMR-OLD',
            'nomor_revisi' => 1,
            'submitted_at' => now(),
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());

        $flow = ApprovalFlow::create([
            'm_document_level_id' => $revision->m_document_level_id,
            'nama_flow' => 'Flow Revisi',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $role = Role::query()->firstOrCreate(['nama_role' => $stage->nama_tahap]);

        Approval::create([
            't_document_id' => $revision->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'role_id' => $role->id,
            'assigned_by' => $submitter->id,
            'assigned_at' => now(),
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $this->assertSame(StatusDocument::APPROVED, $revision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
    }

    public function test_approved_obsolete_request_obsoletes_source_master_document(): void
    {
        $this->ensureApprovalStatuses();

        $submitter = User::factory()->create(['name' => 'Pengaju Awal Master']);
        $obsoleteRequester = User::factory()->create(['name' => 'Pengaju Obsolete Dokumen']);
        $approver = User::factory()->create();
        $source = $this->createDocument($submitter, [
            'nama_dokumen' => 'Master Akan Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBSOLETE',
            'nomor_revisi' => 0,
        ]);
        $approvedDocumentStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $source->update([
            'm_status_document_id' => $approvedDocumentStatus->id,
            'approved_at' => now()->subDay(),
        ]);
        DocumentFile::create([
            't_document_id' => $source->id,
            'type_file' => 'filled_template',
            'path_file' => "documents/{$source->id}/master-obsolete.pdf",
            'uploaded_by' => $submitter->id,
            'original_file_name' => 'master-obsolete.pdf',
            'stored_file_name' => 'master-obsolete.pdf',
            'file_size' => 24000,
        ]);

        $request = Document::create([
            'm_document_level_id' => $source->m_document_level_id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $source->m_document_types_id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $obsoleteRequester->id,
            'official_preparer_id' => $submitter->id,
            'revised_from' => $source->id,
            'request_type' => 'obsolete',
            'nama_dokumen' => 'Master Akan Obsolete',
            'nomor_dokumen' => 'FMPS-SMR-OBSOLETE',
            'nomor_revisi' => $source->nomor_revisi,
            'catatan_revisi' => 'Dokumen sudah tidak digunakan lagi.',
            'submitted_at' => now(),
        ]);
        $request->departments()->sync($source->departments()->pluck('departments.id')->all());

        $flow = ApprovalFlow::create([
            'm_document_level_id' => $request->m_document_level_id,
            'nama_flow' => 'Flow Obsolete',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $role = Role::query()->firstOrCreate(['nama_role' => $stage->nama_tahap]);

        Approval::create([
            't_document_id' => $request->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'role_id' => $role->id,
            'assigned_by' => $submitter->id,
            'assigned_at' => now(),
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $request))
            ->assertOk()
            ->assertSee('Pengajuan Obsolete Dokumen')
            ->assertSee('Review Pengajuan Obsolete')
            ->assertSee('Approval ini akan mengubah dokumen master terkait menjadi obsolete setelah seluruh tahap disetujui.')
            ->assertSee('Pengaju Awal Dokumen')
            ->assertSee('Pengaju Awal Master')
            ->assertSee('Pengaju Obsolete')
            ->assertSee('Pengaju Obsolete Dokumen')
            ->assertSee('Alasan Obsolete')
            ->assertSee('Dokumen sudah tidak digunakan lagi.')
            ->assertSee('Dokumen yang Akan Diobsoletekan')
            ->assertSee('master-obsolete.pdf')
            ->assertSee(route('documents.master.files.show', [$source, $source->files()->firstOrFail()]), false)
            ->assertDontSee('Detail Dokumen Level IV')
            ->assertDontSee('Belum ada file isi dokumen.');

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $request))
            ->assertRedirect(route('documents.approval.show', $request));

        $this->assertSame(StatusDocument::APPROVED, $request->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
    }

    public function test_first_flow_stage_requires_manual_approver_selection(): void
    {
        $this->ensureApprovalStatuses();

        $officialPreparer = User::factory()->create(['name' => 'Penyusun Resmi Default']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'official_preparer_id' => $officialPreparer->id,
        ]);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $firstStage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Penyusun Resmi Default');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document))
            ->assertSessionHasErrors(["stage_approvers.{$firstStage->id}"]);

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->exists());
    }

    public function test_document_control_admin_can_select_first_stage_approver(): void
    {
        $this->ensureApprovalStatuses();

        $officialPreparer = User::factory()->create(['name' => 'Penyusun Resmi Default']);
        $replacementApprover = User::factory()->create(['name' => 'Approver Pengganti']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'official_preparer_id' => $officialPreparer->id,
        ]);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Level II',
        ]);
        $firstStage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [$replacementApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertFalse(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'Dibuat oleh Staff')
            ->exists());
        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $replacementApprover->id)
            ->where('stages', 'Dibuat oleh Staff')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
            ->exists());
    }

    public function test_official_preparer_is_auto_approved_when_assigned_as_flow_approver(): void
    {
        $this->ensureApprovalStatuses();

        $officialPreparer = User::factory()->create(['name' => 'Penyusun Resmi Default']);
        $nextApprover = User::factory()->create(['name' => 'Approver Berikutnya']);
        $submitter = User::factory()->create();
        $document = $this->createDocument($submitter, [
            'official_preparer_id' => $officialPreparer->id,
        ]);
        $documentControlAdmin = $this->documentControlAdmin($document->departments()->firstOrFail());
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
        $signedAt = now()->subMinutes(10);
        $officialPreparerRole = Role::query()->firstOrCreate(['nama_role' => 'Penyusun Resmi']);

        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id,
            'user_id' => $officialPreparer->id,
            'role_id' => $officialPreparerRole->id,
            'assigned_by' => $submitter->id,
            'assigned_at' => $signedAt,
            'responded_at' => $signedAt,
            'stages' => 'TTD Penyusun Resmi',
            'catatan' => 'Tanda tangan penyusun resmi tercatat saat submit dokumen.',
        ]);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertDontSee('TTD Penyusun Resmi')
            ->assertDontSee('Tanda tangan penyusun resmi tercatat saat submit dokumen.');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $firstStage->id => [$officialPreparer->id],
                    $secondStage->id => [$nextApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(
            ApprovalStatus::APPROVED,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $officialPreparer->id)
                ->where('stages', 'Dibuat oleh Staff')
                ->firstOrFail()
                ->status
                ->kode_status,
        );
        $this->assertNotNull(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'Dibuat oleh Staff')
            ->firstOrFail()
            ->responded_at);
        $this->assertSame(
            ApprovalStatus::PENDING,
            Approval::query()
                ->where('t_document_id', $document->id)
                ->where('user_id', $nextApprover->id)
                ->where('stages', 'Diperiksa oleh Manager')
                ->firstOrFail()
                ->status
                ->kode_status,
        );

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Dokumen Pengujian');

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Dokumen Pengujian')
            ->assertSee('Dibuat oleh Staff')
            ->assertSee('Disetujui');
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
            ['nama_status' => $this->approvalStatusLabel($statusCode)],
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

    private function ensureApprovalStatuses(): void
    {
        foreach ([ApprovalStatus::PENDING, ApprovalStatus::WAITING, ApprovalStatus::APPROVED, ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED] as $statusCode) {
            ApprovalStatus::query()->firstOrCreate(
                ['kode_status' => $statusCode],
                ['nama_status' => $this->approvalStatusLabel($statusCode)],
            );
        }
    }

    private function approvalStatusLabel(string $statusCode): string
    {
        return match ($statusCode) {
            ApprovalStatus::PENDING => 'Dalam Review',
            ApprovalStatus::WAITING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::TERMINATED => 'Dihentikan',
            default => $statusCode,
        };
    }

    private function documentControlAdmin(Department $department): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $permissions = collect([
            [
                'code' => 'documents.inbox.view',
                'name' => 'Lihat Inbox Approval',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.inbox',
                'action' => 'view',
            ],
            [
                'code' => 'documents.approval.assign',
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        ])->map(fn (array $permission): Permission => Permission::query()->firstOrCreate(
            ['code' => $permission['code']],
            $permission,
        ));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        $user = User::factory()->create(['m_department_id' => $department->id]);
        $user->roles()->attach($role);

        return $user->refresh();
    }
}
