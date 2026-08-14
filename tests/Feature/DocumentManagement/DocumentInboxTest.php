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
            ->assertSee('Dalam Review')
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
            ->assertSee('Assign')
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
            ->assertSee('Assign')
            ->assertSee(route('documents.approval.show', $document));
    }

    public function test_document_control_admin_from_unrelated_department_cannot_see_proposed_document_to_assign(): void
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
            ->assertDontSee('Dokumen Assign Department Lain')
            ->assertDontSee('PS-SMR-OTHER-DEPT');
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
            ->assertSee('Assign');
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

    public function test_document_control_admin_from_unrelated_department_cannot_open_proposed_document_detail(): void
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
            ->assertForbidden();
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
            ->assertSee('Assign Approver')
            ->assertSee('Approval Flow Dokumen Level II : Prosedur SKMBS')
            ->assertSee('Assignment approver dikelola oleh Admin Kontrol Dokumen department terkait.')
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
            ->assertRedirect(route('documents.approval.show', $document));

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
            ->assertRedirect(route('documents.approval.show', $document));

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

    public function test_first_flow_stage_defaults_to_official_preparer_when_assignment_is_saved_directly(): void
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
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertTrue(Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'Dibuat oleh Staff')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
            ->exists());
    }

    public function test_document_control_admin_can_replace_default_first_stage_approver_before_save(): void
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
