<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_page_shows_only_approved_documents(): void
    {
        $user = $this->userWithPermission('documents.master.view');
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

    public function test_master_download_logs_master_document_number_snapshot(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);

        $source = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Sumber Revisi',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 0,
        ]);
        $revision = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Sumber Revisi',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 1,
            'revised_from' => $source->id,
            'request_type' => null,
        ]);

        Storage::disk('local')->put('documents/revision-master.pdf', 'master revision content');
        $file = DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_content',
            'path_file' => 'documents/revision-master.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'revision-master.pdf',
            'stored_file_name' => 'revision-master.pdf',
            'file_size' => 23,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.files.show', [$revision, $file]))
            ->assertOk();

        $log = DocumentDownloadLog::query()->firstOrFail();

        $this->assertSame('master', $log->download_context);
        $this->assertSame('PS-SMR-SNAP', $log->document_number_snapshot);
        $this->assertSame('Prosedur Sumber Revisi', $log->document_name_snapshot);
        $this->assertSame(1, $log->document_revision_snapshot);
    }

    public function test_master_page_does_not_show_approved_obsolete_request_transaction(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $source = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Akan Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS-REQ',
        ]);
        $obsoleteRequest = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Transaksi Obsolete Master',
            'nomor_dokumen' => 'FMPS-SMR-OBS-REQ',
            'revised_from' => $source->id,
            'request_type' => 'obsolete',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertDontSee('Master Akan Obsolete')
            ->assertDontSee('Transaksi Obsolete Master')
            ->assertDontSee('PS-SMR-OBS-REQ')
            ->assertDontSee('FMPS-SMR-OBS-REQ');

        $this->actingAs($user)
            ->get(route('documents.master.show', $obsoleteRequest))
            ->assertNotFound();
    }

    public function test_master_page_groups_obsolete_revision_inside_master_document(): void
    {
        $user = $this->userWithPermission('documents.master.view');
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
            ->assertSee('Tgl Obsolete')
            ->assertSee('PS-SMR-001')
            ->assertSee('Obsolete');
    }

    public function test_master_page_shows_latest_approved_revision_as_primary_row(): void
    {
        $user = $this->userWithPermission('documents.master.view');
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
            ->assertSee('Tgl Obsolete')
            ->assertSee('00.00');

        $this->assertLessThan(
            strpos($response->getContent(), 'Tgl Obsolete'),
            strpos($response->getContent(), 'Instruksi Kerja Revisi Aktif'),
        );
        $response->assertSee(route('documents.master.show', $latestRevision), false);
    }

    public function test_master_page_obsolete_dropdown_uses_master_document_number_for_revision_forms(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);
        $obsoleteRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Revisi Pertama',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDays(2),
        ]);
        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Revisi Aktif',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Revisi Aktif')
            ->assertSee('PS-SMR-001')
            ->assertSee('00.01')
            ->assertSee(route('documents.obsolete.show', $obsoleteRevision), false);

        $this->assertStringNotContainsString(
            '<td class="px-5 py-4 font-semibold uppercase tracking-wide text-slate-700">'.PHP_EOL.'                                                                FMPS-SMR-001',
            $response->getContent(),
        );
    }

    public function test_obsolete_page_shows_only_obsolete_documents(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $obsoleteDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Lama Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
            'nomor_revisi' => 2,
        ]);
        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Master Aktif',
            'nomor_dokumen' => 'PS-SMR-ACT',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Dokumen Obsolete')
            ->assertDontSee('Tambah Dokumen Obsolete')
            ->assertSee('Dokumen Lama Obsolete')
            ->assertSee('PS-SMR-OBS')
            ->assertSee('00.02')
            ->assertSee('Obsolete')
            ->assertSee(route('documents.obsolete.show', $obsoleteDocument), false)
            ->assertDontSee('Dokumen Master Aktif')
            ->assertDontSee('PS-SMR-ACT');
    }

    public function test_obsolete_page_does_not_show_obsolete_request_transaction(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $masterDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Obsolete Asli',
            'nomor_dokumen' => 'PS-SMR-OBS-REAL',
        ]);
        $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Transaksi Obsolete Approved',
            'nomor_dokumen' => 'FMPS-SMR-OBS-REQ',
            'nomor_revisi' => 1,
            'revised_from' => $masterDocument->id,
            'request_type' => 'obsolete',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Dokumen Obsolete Asli')
            ->assertDontSee('Transaksi Obsolete Approved')
            ->assertDontSee('FMPS-SMR-OBS-REQ');
    }

    public function test_obsolete_page_groups_versions_under_latest_obsolete_revision(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group',
            'nomor_dokumen' => 'PS-SMR-OBS-GRP',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);
        $firstRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group Revisi 1',
            'nomor_dokumen' => 'FMPS-SMR-OBS-GRP',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDays(2),
        ]);
        $latestRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group Revisi 2',
            'nomor_dokumen' => 'FMPS-SMR-OBS-GRP',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Tampilkan riwayat versi obsolete')
            ->assertSee('Prosedur Obsolete Group Revisi 2')
            ->assertSee('00.02')
            ->assertSee('Prosedur Obsolete Group Revisi 1')
            ->assertSee('00.01')
            ->assertSee('00.00')
            ->assertSee(route('documents.obsolete.show', $latestRevision), false)
            ->assertSee(route('documents.obsolete.show', $firstRevision), false)
            ->assertSee(route('documents.obsolete.show', $rootDocument), false);

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, route('documents.obsolete.show', $firstRevision)),
            strpos($content, route('documents.obsolete.show', $latestRevision)),
        );
        $this->assertLessThan(
            strpos($content, route('documents.obsolete.show', $rootDocument)),
            strpos($content, route('documents.obsolete.show', $firstRevision)),
        );
    }

    public function test_obsolete_page_shows_add_button_for_user_with_create_permission(): void
    {
        $user = $this->userWithPermission('documents.obsolete.create');

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Tambah Dokumen Obsolete')
            ->assertSee(route('documents.master'), false);
    }

    public function test_user_role_can_read_obsolete_list_and_detail(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $userRole = Role::create(['nama_role' => 'User']);
        $viewPermission = Permission::create([
            'code' => 'documents.obsolete.view',
            'name' => 'Lihat Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete',
            'action' => 'view',
        ]);
        $detailPermission = Permission::create([
            'code' => 'documents.obsolete.detail',
            'name' => 'Lihat Detail Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete.show',
            'action' => 'view',
        ]);
        $userRole->permissions()->sync([$viewPermission->id, $detailPermission->id]);
        $user = User::factory()->create();
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Obsolete Untuk User',
            'nomor_dokumen' => 'PS-SMR-USER-OBS',
        ]);

        $this->actingAs($user->fresh())
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Manajemen Dokumen')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('href="'.route('documents.obsolete').'"', false)
            ->assertSee('Dokumen Obsolete Untuk User')
            ->assertSee(route('documents.obsolete.show', $document), false);

        $this->actingAs($user->fresh())
            ->get(route('documents.obsolete.show', $document))
            ->assertOk()
            ->assertSee('Detail Dokumen Obsolete')
            ->assertSee('PS-SMR-USER-OBS');
    }

    public function test_obsolete_page_requires_view_permission_when_permissions_exist(): void
    {
        Permission::create([
            'code' => 'documents.obsolete.view',
            'name' => 'Lihat Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete',
            'action' => 'view',
        ]);
        $role = Role::create(['nama_role' => 'Viewer Tanpa Obsolete']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertForbidden();
    }

    public function test_obsolete_page_uses_master_document_number_for_revision_forms(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
        ]);
        $obsoleteRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Revisi Obsolete',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Prosedur Revisi Obsolete')
            ->assertSee('PS-SMR-001')
            ->assertSee('00.01')
            ->assertSee(route('documents.obsolete.show', $obsoleteRevision), false);

        $this->assertStringNotContainsString(
            '<td class="px-3 py-4 font-semibold text-slate-700">FMPS-SMR-001</td>',
            $response->getContent(),
        );
    }

    public function test_approved_level_four_revision_becomes_master_and_groups_old_master_as_obsolete(): void
    {
        $submitter = $this->userWithPermission('documents.master.view');
        $approver = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::PENDING, 'nama_status' => 'Dalam Review']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::WAITING, 'nama_status' => 'Menunggu']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::APPROVED, 'nama_status' => 'Disetujui']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::REJECTED, 'nama_status' => 'Ditolak']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::TERMINATED, 'nama_status' => 'Dibatalkan']);

        $source = $this->createDocument($submitter, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Ikatan Dinas SSO',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDay(),
        ]);
        $levelFour = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);

        $revision = Document::create([
            'm_document_level_id' => $levelFour->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Ikatan Dinas SSO',
            'nomor_dokumen' => 'FMPS-KSA-02',
            'nomor_revisi' => 1,
            'submitted_at' => now(),
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());
        DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_content',
            'path_file' => "documents/{$revision->id}/dokumen-revisi.pdf",
            'uploaded_by' => $submitter->id,
            'original_file_name' => 'dokumen-revisi.pdf',
            'stored_file_name' => 'dokumen-revisi.pdf',
            'file_size' => 24000,
        ]);
        DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_form',
            'path_file' => "documents/{$revision->id}/lembar-revisi.pdf",
            'uploaded_by' => $submitter->id,
            'original_file_name' => 'lembar-revisi.pdf',
            'stored_file_name' => 'lembar-revisi.pdf',
            'file_size' => 12000,
        ]);

        $flow = ApprovalFlow::create([
            'm_document_level_id' => $source->m_document_level_id,
            'nama_flow' => 'Flow Revisi Prosedur',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Disahkan Oleh',
            'nama_tahap' => 'Superintendent',
        ]);

        Approval::create([
            't_document_id' => $revision->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $submitter->id,
            'assigned_at' => now(),
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $source->refresh();
        $masterRevision = Document::query()
            ->whereNull('request_type')
            ->where('revised_from', $source->id)
            ->where('nomor_dokumen', 'PS-KSA-02')
            ->where('nomor_revisi', 1)
            ->firstOrFail();

        $this->assertSame(StatusDocument::APPROVED, $revision->status->nama_status);
        $this->assertSame('revision', $revision->request_type);
        $this->assertSame(StatusDocument::OBSOLETE, $source->status->nama_status);
        $this->assertNotSame($source->m_document_level_id, $revision->m_document_level_id);
        $this->assertSame('FMPS-KSA-02', $revision->nomor_dokumen);
        $this->assertSame(1, $revision->nomor_revisi);
        $this->assertSame($source->m_document_level_id, $masterRevision->m_document_level_id);
        $this->assertSame($source->m_document_types_id, $masterRevision->m_document_types_id);
        $this->assertSame('PS-KSA-02', $masterRevision->nomor_dokumen);
        $this->assertSame(1, $masterRevision->nomor_revisi);

        $this->actingAs($submitter)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Ikatan Dinas SSO')
            ->assertSee('PS-KSA-02')
            ->assertSee('00.01')
            ->assertDontSee('FMPS-KSA-02')
            ->assertSee('Tgl Obsolete')
            ->assertSee('00.00')
            ->assertSee('Obsolete');

        $this->actingAs($submitter)
            ->get(route('documents.master.show', $masterRevision))
            ->assertOk()
            ->assertSee('Nomor Dokumen Revisi')
            ->assertSee('FMPS-KSA-02')
            ->assertSee('Isi Dokumen Versi Revisi')
            ->assertSee('dokumen-revisi.pdf')
            ->assertSee('Lembar Revisi')
            ->assertSee('lembar-revisi.pdf')
            ->assertDontSee('Belum ada file isi dokumen.');

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $masterRevision))
            ->assertOk()
            ->assertSee('Nomor Dokumen Revisi')
            ->assertSee('FMPS-KSA-02');

        $this->actingAs($submitter)
            ->get(route('documents.master.show', $revision))
            ->assertNotFound();

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('FMPS-KSA-02');
    }

    public function test_master_detail_shows_approval_history_sorted_by_stage_with_response_timestamp(): void
    {
        $viewer = $this->userWithPermission('documents.master.detail');
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
        $viewer = $this->userWithPermission('documents.master.detail');
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
        $sameDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($sameDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Ajukan Revisi')
            ->assertSee(route('documents.create.level', ['level-4', 'revised_from' => $document->id]), false)
            ->assertSee('data-obsolete-modal-open', false);

        $this->actingAs($otherDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertDontSee('Ajukan Revisi');
    }

    public function test_obsolete_button_only_shows_for_user_from_document_department(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Bisa Diobsoletekan',
            'nomor_dokumen' => 'PS-SMR-OBS-BTN',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $obsoleteUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'FN',
            'nama_department' => 'Finance',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($obsoleteUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Obsolete')
            ->assertSee('data-obsolete-modal-open', false);

        $this->actingAs($otherDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertDontSee('data-obsolete-modal-open', false);
    }

    public function test_obsolete_document_detail_uses_obsolete_page_and_is_read_only(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $user = $this->userWithPermission('documents.obsolete.detail');
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Sudah Obsolete',
            'nomor_dokumen' => 'PS-SMR-OLD',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.show', $document))
            ->assertOk()
            ->assertSee('Detail Dokumen Obsolete')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('Obsolete')
            ->assertDontSee('Ajukan Revisi')
            ->assertDontSee('Pengajuan Obsolete')
            ->assertDontSee('Jadikan Master')
            ->assertDontSee('data-obsolete-modal-open', false);

        $this->actingAs($user)
            ->get(route('documents.master.show', $document))
            ->assertNotFound();
    }

    public function test_regular_user_cannot_restore_obsolete_document_as_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $user = $this->userWithoutPermission('documents.obsolete.restore');
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Tidak Boleh Restore',
            'nomor_dokumen' => 'PS-SMR-NO-RESTORE',
        ]);

        $this->actingAs($user)
            ->post(route('documents.obsolete.restore', $document))
            ->assertForbidden();

        $this->assertSame(StatusDocument::OBSOLETE, $document->refresh()->status->nama_status);
    }

    public function test_obsolete_revision_can_be_restored_as_master_and_only_older_versions_become_children(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore',
            'nomor_dokumen' => 'PS-SMR-RESTORE',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(6),
        ]);

        $revisions = collect(range(1, 5))->map(function (int $revisionNumber) use ($owner, $obsoleteStatus, $rootDocument): Document {
            return $this->createDocument($owner, $obsoleteStatus, [
                'nama_dokumen' => 'Prosedur Restore Revisi '.$revisionNumber,
                'nomor_dokumen' => 'FMPS-SMR-RESTORE',
                'nomor_revisi' => $revisionNumber,
                'revised_from' => $rootDocument->id,
                'approved_at' => now()->subDays(6 - $revisionNumber),
            ]);
        });
        $selectedRevision = $revisions->firstWhere('nomor_revisi', 3);
        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.obsolete.show', $selectedRevision))
            ->assertOk()
            ->assertSee('Jadikan Master');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $selectedRevision))
            ->assertRedirect(route('documents.master.show', $selectedRevision));

        $this->assertSame(StatusDocument::APPROVED, $selectedRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $rootDocument->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $revisions->firstWhere('nomor_revisi', 5)->refresh()->status->nama_status);

        $response = $this->actingAs($documentControlAdmin)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Restore Revisi 3')
            ->assertSee('00.03')
            ->assertSee('00.00')
            ->assertSee('00.01')
            ->assertSee('00.02')
            ->assertSee(route('documents.master.show', $selectedRevision), false);

        $this->assertStringNotContainsString('00.04', $response->getContent());
        $this->assertStringNotContainsString('00.05', $response->getContent());
        $this->assertStringNotContainsString(route('documents.master.show', $revisions->firstWhere('nomor_revisi', 4)), $response->getContent());
        $this->assertStringNotContainsString(route('documents.master.show', $revisions->firstWhere('nomor_revisi', 5)), $response->getContent());
    }

    public function test_approved_obsolete_request_transaction_does_not_block_restore_as_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Dengan Request',
            'nomor_dokumen' => 'PS-SMR-REQ',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);

        $selectedRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Dengan Request Revisi',
            'nomor_dokumen' => 'FMPS-SMR-REQ',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDays(2),
        ]);

        $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Request Obsolete Approved',
            'nomor_dokumen' => 'FMPS-SMR-REQ',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'request_type' => 'obsolete',
            'approved_at' => now()->subDay(),
        ]);

        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertDontSee('Request Obsolete Approved');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $selectedRevision))
            ->assertRedirect(route('documents.master.show', $selectedRevision))
            ->assertSessionMissing('restore_warning');

        $this->assertSame(StatusDocument::APPROVED, $selectedRevision->refresh()->status->nama_status);
    }

    public function test_obsolete_revision_restore_is_blocked_when_same_family_still_has_active_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked',
            'nomor_dokumen' => 'PS-SMR-BLOCK',
            'nomor_revisi' => 0,
        ]);
        $olderRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 2',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
        ]);
        $activeMaster = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 3',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 3,
            'revised_from' => $rootDocument->id,
        ]);
        $newerRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 5',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 5,
            'revised_from' => $rootDocument->id,
        ]);
        $documentDepartment = $rootDocument->departments()->firstOrFail();
        $olderRevision->departments()->sync([$documentDepartment->id]);
        $activeMaster->departments()->sync([$documentDepartment->id]);
        $newerRevision->departments()->sync([$documentDepartment->id]);
        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $olderRevision))
            ->assertRedirect(route('documents.obsolete.show', $olderRevision))
            ->assertSessionHas('restore_warning.message', 'Versi terbaru 00.03 masih menjadi master. Silakan obsolete-kan versi terbaru dulu.');

        $this->assertSame(StatusDocument::OBSOLETE, $olderRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $activeMaster->refresh()->status->nama_status);

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $newerRevision))
            ->assertRedirect(route('documents.obsolete.show', $newerRevision))
            ->assertSessionHas('restore_warning.message', 'Versi 00.03 masih menjadi master. Silakan obsolete-kan versi 00.03 dulu.');

        $this->assertSame(StatusDocument::OBSOLETE, $newerRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $activeMaster->refresh()->status->nama_status);
    }

    public function test_user_from_other_department_cannot_submit_master_obsolete_request(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Tidak Boleh Obsolete',
            'nomor_dokumen' => 'PS-SMR-NO-OBS',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'FN',
            'nama_department' => 'Finance',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($otherDepartmentUser)
            ->post(route('documents.master.obsolete', $document), [
                'catatan_obsolete' => 'Dokumen sudah tidak digunakan.',
            ])
            ->assertForbidden();

        $this->assertFalse(
            Document::query()
                ->where('revised_from', $document->id)
                ->where('request_type', 'obsolete')
                ->exists(),
        );
    }

    public function test_user_from_document_department_can_submit_master_obsolete_request(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Jadi Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $obsoleteUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);

        $this->actingAs($obsoleteUser)
            ->post(route('documents.master.obsolete', $document), [
                'catatan_obsolete' => 'Dokumen sudah tidak digunakan.',
            ])
            ->assertRedirect(route('documents.inbox'));

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);

        $request = Document::query()
            ->where('revised_from', $document->id)
            ->where('request_type', 'obsolete')
            ->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $request->status->nama_status);
        $this->assertSame($document->documentType->nama_types, $request->documentType->nama_types);
        $this->assertSame($document->documentLevel->kode, $request->documentLevel->kode);
        $this->assertSame('PS-SMR-OBS', $request->nomor_dokumen);
        $this->assertSame('Dokumen sudah tidak digunakan.', $request->catatan_revisi);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Manajemen Dokumen',
                'route' => match ($permissionCode) {
                    'documents.master.view' => 'documents.master',
                    'documents.master.detail' => 'documents.master.show',
                    'documents.obsolete.detail' => 'documents.obsolete.show',
                    'documents.obsolete.restore' => 'documents.obsolete.restore',
                    default => 'documents.obsolete',
                },
                'action' => match ($permissionCode) {
                    'documents.obsolete.create' => 'create',
                    'documents.obsolete.restore' => 'restore',
                    default => 'view',
                },
            ],
        );
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role '.$permissionCode]);
        $user = User::factory()->create();

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $masterDetailPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.master.detail'],
            [
                'name' => 'documents.master.detail',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
        );

        $role->permissions()->syncWithoutDetaching([$masterDetailPermission->id]);

        if (in_array($permissionCode, ['documents.obsolete.create', 'documents.obsolete.restore'], true)) {
            $viewPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.obsolete.view'],
                [
                    'name' => 'documents.obsolete.view',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.obsolete',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$viewPermission->id]);
        }

        if ($permissionCode === 'documents.obsolete.restore') {
            $detailPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.obsolete.detail'],
                [
                    'name' => 'documents.obsolete.detail',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.obsolete.show',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$detailPermission->id]);

            $masterViewPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.master.view'],
                [
                    'name' => 'documents.master.view',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.master',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$masterViewPermission->id]);
        }
        $user->roles()->attach($role);

        return $user->refresh();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function userWithoutPermission(string $permissionCode, array $attributes = []): User
    {
        Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Manajemen Dokumen',
                'route' => match ($permissionCode) {
                    'documents.master.view' => 'documents.master',
                    'documents.master.detail' => 'documents.master.show',
                    'documents.obsolete.detail' => 'documents.obsolete.show',
                    'documents.obsolete.restore' => 'documents.obsolete.restore',
                    default => 'documents.obsolete',
                },
                'action' => match ($permissionCode) {
                    'documents.obsolete.create' => 'create',
                    'documents.obsolete.restore' => 'restore',
                    default => 'view',
                },
            ],
        );
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role tanpa '.$permissionCode]);
        $user = User::factory()->create($attributes);
        $masterDetailPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.master.detail'],
            [
                'name' => 'documents.master.detail',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
        );

        $role->permissions()->syncWithoutDetaching([$masterDetailPermission->id]);
        $user->roles()->attach($role);

        return $user->refresh();
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
