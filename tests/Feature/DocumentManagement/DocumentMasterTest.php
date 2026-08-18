<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_page_shows_only_approved_documents(): void
    {
        $user = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $proposedStatus = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);

        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Master Approved',
            'nomor_dokumen' => 'PS-SMR-APP',
        ]);
        $this->createDocument($user, $proposedStatus, [
            'nama_dokumen' => 'Dokumen Masih Proposed',
            'nomor_dokumen' => 'PS-SMR-PROP',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Dokumen Master Approved')
            ->assertSee('PS-SMR-APP')
            ->assertSee('Master')
            ->assertDontSee('Dokumen Masih Proposed')
            ->assertDontSee('PS-SMR-PROP');
    }

    public function test_master_page_groups_obsolete_revision_inside_master_document(): void
    {
        $user = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $oldDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Pengendalian Dokumen',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
        ]);

        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Pengendalian Dokumen Revisi',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $oldDocument->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Pengendalian Dokumen Revisi')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('Prosedur Pengendalian Dokumen')
            ->assertSee('Obsolete');
    }

    public function test_master_page_shows_latest_approved_revision_as_primary_row(): void
    {
        $user = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $oldDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Instruksi Kerja Lama',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(2),
        ]);

        $latestRevision = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Kerja Revisi Aktif',
            'nomor_dokumen' => 'FMIK-SMR-010',
            'nomor_revisi' => 1,
            'revised_from' => $oldDocument->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Instruksi Kerja Revisi Aktif')
            ->assertSee('IK-SMR-010')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('Instruksi Kerja Lama');

        $this->assertLessThan(
            strpos($response->getContent(), 'Dokumen Obsolete'),
            strpos($response->getContent(), 'Instruksi Kerja Revisi Aktif'),
        );
        $response->assertSee(route('documents.master.show', $latestRevision), false);
    }

    public function test_master_detail_shows_approval_history_sorted_by_stage_with_response_timestamp(): void
    {
        $viewer = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $approvedApprovalStatus = ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        $document = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Riwayat',
            'nomor_dokumen' => 'PS-SMR-HISTORY',
        ]);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Master',
        ]);
        ApprovalFlowStage::create([
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);
        ApprovalFlowStage::create([
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 2,
            'keterangan' => 'Disetujui oleh',
            'nama_tahap' => 'Direktur Utama',
        ]);
        $staffRole = Role::create(['nama_role' => 'Staff']);
        $directorRole = Role::create(['nama_role' => 'Direktur Utama']);
        $staffApprover = User::factory()->create(['name' => 'Staff Approver']);
        $directorApprover = User::factory()->create(['name' => 'Director Approver']);

        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => $approvedApprovalStatus->id,
            'user_id' => $directorApprover->id,
            'role_id' => $directorRole->id,
            'assigned_by' => $viewer->id,
            'assigned_at' => now()->subDay(),
            'responded_at' => now()->setDate(2026, 8, 18)->setTime(15, 10, 20),
            'stages' => 'Disetujui oleh Direktur Utama',
        ]);
        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => $approvedApprovalStatus->id,
            'user_id' => $staffApprover->id,
            'role_id' => $staffRole->id,
            'assigned_by' => $viewer->id,
            'assigned_at' => now(),
            'responded_at' => now()->setDate(2026, 8, 18)->setTime(14, 25, 36),
            'stages' => 'Dibuat oleh Staff',
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Dibuat oleh Staff')
            ->assertSee('Diproses pada 18 Aug 2026 14:25:36')
            ->assertSee('Disetujui oleh Direktur Utama')
            ->assertDontSee('Tahap 1')
            ->assertDontSee('Tahap 2');

        $this->assertLessThan(
            strpos($response->getContent(), 'Disetujui oleh Direktur Utama'),
            strpos($response->getContent(), 'Dibuat oleh Staff'),
        );
    }

    public function test_master_detail_for_revision_shows_parent_and_revision_numbers_without_reference_rows(): void
    {
        $viewer = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $referenceDocument = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Acuan Lama',
            'nomor_dokumen' => 'PS-SMR-REF',
        ]);
        $source = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Induk',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
            'reference' => $referenceDocument->id,
        ]);
        $revision = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Revisi Aktif',
            'nomor_dokumen' => 'FMIK-SMR-010',
            'nomor_revisi' => 1,
            'revised_from' => $source->id,
            'reference' => $referenceDocument->id,
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('documents.master.show', $revision))
            ->assertOk()
            ->assertSee('Nomor Dokumen')
            ->assertSee('IK-SMR-010')
            ->assertSee('Nomor Dokumen Revisi')
            ->assertSee('FMIK-SMR-010')
            ->assertSee('00.01')
            ->assertDontSee('Dokumen Acuan')
            ->assertDontSee('Revisi Dari')
            ->assertDontSee('PS-SMR-REF - Dokumen Acuan Lama')
            ->assertDontSee('IK-SMR-010 - Revisi 00.00');

        $this->assertLessThan(
            strpos($response->getContent(), 'Nomor Dokumen Revisi'),
            strpos($response->getContent(), 'Nomor Dokumen'),
        );
    }

    public function test_revision_button_only_shows_for_user_from_document_department(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Bisa Direvisi',
            'nomor_dokumen' => 'PS-SMR-REV',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $sameDepartmentUser = User::factory()->create(['m_department_id' => $documentDepartment->id]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $otherDepartmentUser = User::factory()->create(['m_department_id' => $otherDepartment->id]);

        $this->actingAs($sameDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Ajukan Revisi')
            ->assertSee(route('documents.create.level', ['level-2', 'revised_from' => $document->id]), false);

        $this->actingAs($otherDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertDontSee('Ajukan Revisi');
    }

    private function createDocument(User $user, StatusDocument $status, array $attributes = []): Document
    {
        $documentType = DocumentType::create(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $department = Department::create([
            'kode_department' => fake()->unique()->lexify('??'),
            'nama_department' => fake()->unique()->words(2, true),
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
            'nama_dokumen' => 'Dokumen Master',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'approved_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }
}
