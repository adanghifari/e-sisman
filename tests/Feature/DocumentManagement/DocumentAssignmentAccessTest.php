<?php

namespace Tests\Feature\DocumentManagement;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAssignmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_control_admin_with_assign_permission_can_assign_document(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $document = $this->proposedDocumentForDepartments([$department]);

        $this->assertTrue($admin->isDocumentControlAdmin());
        $this->assertTrue($admin->canAssignDocument($document));
    }

    public function test_document_control_admin_without_assign_permission_cannot_assign_document(): void
    {
        $userDepartment = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $documentDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $admin = $this->documentControlAdmin($userDepartment, withAssignPermission: false);
        $document = $this->proposedDocumentForDepartments([$documentDepartment]);

        $this->assertTrue($admin->isDocumentControlAdmin());
        $this->assertFalse($admin->canAssignDocument($document));
    }

    public function test_regular_user_from_related_department_cannot_assign_document(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $user = User::factory()->create(['m_department_id' => $department->id]);
        $document = $this->proposedDocumentForDepartments([$department]);

        $this->assertFalse($user->isDocumentControlAdmin());
        $this->assertFalse($user->canAssignDocument($document));
    }

    public function test_user_with_assign_permission_can_assign_document_from_any_department(): void
    {
        $userDepartment = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $documentDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $role = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.assign'],
            [
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        );
        $admin = User::factory()->create(['m_department_id' => $userDepartment->id]);
        $admin->roles()->attach($role);
        $role->permissions()->attach($permission);
        $document = $this->proposedDocumentForDepartments([$documentDepartment]);

        $this->assertTrue($admin->refresh()->canAssignDocument($document));
    }

    public function test_document_control_admin_with_update_permission_can_update_submitted_document_before_assignment(): void
    {
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'metadata',
                'nama_dokumen' => 'Dokumen hasil koreksi admin',
                'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
                'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $document->official_preparer_id,
                'nomor_dokumen_suffix' => '77',
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));
        $this->assertSame('Dokumen hasil koreksi admin', $document->refresh()->nama_dokumen);
        $this->assertSame('PS-'.$document->businessFunction->kode.'-77', $document->nomor_dokumen);
    }

    public function test_submitted_document_update_is_blocked_after_approver_assigned(): void
    {
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $approver = User::factory()->create();

        $document->approvals()->create([
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'stages' => 'Approval 1',
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'metadata',
                'nama_dokumen' => 'Tidak boleh berubah',
                'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
                'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $document->official_preparer_id,
                'nomor_dokumen_suffix' => '88',
            ]);

        $response->assertForbidden();
        $this->assertNotSame('Tidak boleh berubah', $document->refresh()->nama_dokumen);
    }

    public function test_submitted_attachment_replacement_updates_file_record(): void
    {
        Storage::fake('local');
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $document->forceFill(['nomor_dokumen' => 'PS-QA-01'])->save();
        $attachment = $this->attachmentFile($document, 'lampiran-lama.pdf', 'PS-QA-01-02');

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'files',
                'existing_attachment_titles' => [$attachment->id => 'Lampiran A'],
                'existing_attachment_orders' => [$attachment->id => 1],
                'replacement_attachments' => [
                    $attachment->id => UploadedFile::fake()->create('lampiran-baru.pdf', 20, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));
        $attachment->refresh();
        $this->assertSame('lampiran-baru.pdf', $attachment->original_file_name);
        $this->assertStringEndsWith('-02', $attachment->document_number);
    }

    public function test_submitted_content_file_replacement_updates_file_record(): void
    {
        Storage::fake('local');
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $document->forceFill(['nomor_dokumen' => 'PS-QA-01'])->save();
        $contentFile = $this->documentFile($document, 'filled_template', 'template-lama.pdf', 'PS-QA-01');

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'files',
                'replacement_files' => [
                    $contentFile->id => UploadedFile::fake()->create('template-baru.pdf', 20, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));
        $contentFile->refresh();
        $this->assertSame('template-baru.pdf', $contentFile->original_file_name);
        $this->assertSame('PS-QA-01', $contentFile->document_number);
    }

    public function test_submitted_attachment_delete_renumbers_remaining_attachments_from_02(): void
    {
        Storage::fake('local');
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $document->forceFill(['nomor_dokumen' => 'PS-QA-01'])->save();
        $firstAttachment = $this->attachmentFile($document, 'lampiran-1.pdf', 'PS-QA-01-02', 1);
        $secondAttachment = $this->attachmentFile($document, 'lampiran-2.pdf', 'PS-QA-01-03', 2);

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'files',
                'remove_existing_files' => [$firstAttachment->id],
                'existing_attachment_titles' => [$secondAttachment->id => 'Lampiran 2'],
                'existing_attachment_orders' => [$secondAttachment->id => 1],
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));
        $this->assertDatabaseMissing('t_document_files', ['id' => $firstAttachment->id]);
        $this->assertStringEndsWith('-02', $secondAttachment->refresh()->document_number);
    }

    public function test_submitted_middle_attachment_delete_closes_number_gap(): void
    {
        Storage::fake('local');
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $document->forceFill(['nomor_dokumen' => 'PS-QA-01'])->save();
        $firstAttachment = $this->attachmentFile($document, 'lampiran-1.pdf', 'PS-QA-01-02', 1);
        $middleAttachment = $this->attachmentFile($document, 'lampiran-2.pdf', 'PS-QA-01-03', 2);
        $lastAttachment = $this->attachmentFile($document, 'lampiran-3.pdf', 'PS-QA-01-04', 3);

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'files',
                'remove_existing_files' => [$middleAttachment->id],
                'existing_attachment_titles' => [
                    $firstAttachment->id => 'Lampiran 1',
                    $lastAttachment->id => 'Lampiran 3',
                ],
                'existing_attachment_orders' => [
                    $firstAttachment->id => 1,
                    $lastAttachment->id => 2,
                ],
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));
        $this->assertDatabaseMissing('t_document_files', ['id' => $middleAttachment->id]);
        $this->assertSame('FMPS-QA-01-02', $firstAttachment->refresh()->document_number);
        $this->assertSame('FMPS-QA-01-03', $lastAttachment->refresh()->document_number);
    }

    public function test_submitted_attachment_update_compacts_active_attachment_numbers(): void
    {
        Storage::fake('local');
        $this->ensureApprovalStatuses();
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $admin = $this->documentControlAdmin($department);
        $this->grantPermission($admin, 'documents.approval.update-submitted', 'Edit Dokumen Sebelum Assign Approver', 'documents.approval.update-submitted', 'update');
        $document = $this->proposedDocumentForDepartments([$department]);
        $document->forceFill(['nomor_dokumen' => 'PS-QA-01', 'request_type' => 'revision'])->save();

        $historicalDocument = $document->replicate(['submitted_at']);
        $historicalDocument->forceFill([
            'm_status_document_id' => StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED])->id,
            'submitted_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
        ])->save();
        $this->attachmentFile($historicalDocument, 'lampiran-historis.pdf', 'FMPS-QA-01-02', 1);

        $this->documentFile($document, 'revision_form', 'lembar-revisi.pdf', 'FMPS-QA-01-01');
        $firstAttachment = $this->attachmentFile($document, 'test-lampiran.pdf', 'FMPS-QA-01-03', 1);
        $secondAttachment = $this->attachmentFile($document, 'daftar-hadir.pdf', 'FMPS-QA-01-04', 2);

        $response = $this
            ->actingAs($admin)
            ->post(route('documents.approval.update-submitted', $document), [
                '_update_scope' => 'files',
                'existing_attachment_titles' => [
                    $firstAttachment->id => 'Test Lampiran',
                    $secondAttachment->id => 'Daftar Hadir',
                ],
                'existing_attachment_orders' => [
                    $firstAttachment->id => 1,
                    $secondAttachment->id => 2,
                ],
            ]);

        $response->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame('FMPS-QA-01-02', $firstAttachment->refresh()->document_number);
        $this->assertSame('FMPS-QA-01-03', $secondAttachment->refresh()->document_number);
    }

    public function test_document_control_admin_can_assign_multi_department_document_when_one_department_matches(): void
    {
        $qa = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $hr = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $admin = $this->documentControlAdmin($hr);
        $document = $this->proposedDocumentForDepartments([$qa, $hr]);

        $this->assertTrue($admin->canAssignDocument($document));
    }

    private function documentControlAdmin(Department $department, bool $withAssignPermission = true): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $user = User::factory()->create(['m_department_id' => $department->id]);

        if ($withAssignPermission) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'documents.approval.assign'],
                [
                    'name' => 'Assign Approver Dokumen',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.approval.assign',
                    'action' => 'assign',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role);

        return $user->refresh();
    }

    private function grantPermission(User $user, string $code, string $name, string $route, string $action): void
    {
        $role = $user->roles()->firstOrFail();
        $permission = Permission::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'module' => 'Manajemen Dokumen',
                'route' => $route,
                'action' => $action,
            ],
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('roles');
    }

    private function ensureApprovalStatuses(): void
    {
        foreach ([
            ApprovalStatus::PENDING => 'Dalam Review',
            ApprovalStatus::WAITING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::TERMINATED => 'Dihentikan',
        ] as $code => $name) {
            ApprovalStatus::query()->firstOrCreate(
                ['kode_status' => $code],
                ['nama_status' => $name],
            );
        }
    }

    private function attachmentFile(Document $document, string $fileName, string $documentNumber, int $order = 1)
    {
        return $this->documentFile($document, 'attachment', $fileName, $documentNumber, $order);
    }

    private function documentFile(Document $document, string $type, string $fileName, string $documentNumber, ?int $order = null)
    {
        $path = "documents/{$document->id}/{$fileName}";
        Storage::disk('local')->put($path, 'PDF');

        return $document->files()->create([
            'type_file' => $type,
            'document_number' => $documentNumber,
            'attachment_title' => $type === 'attachment' ? pathinfo($fileName, PATHINFO_FILENAME) : null,
            'attachment_order' => $order,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => $fileName,
            'stored_file_name' => $fileName,
            'file_size' => 3,
        ]);
    }

    /**
     * @param  array<int, Department>  $departments
     */
    private function proposedDocumentForDepartments(array $departments): Document
    {
        $submitter = User::factory()->create();
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::query()->create(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::query()->create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::query()->create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $document = Document::query()->create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => fake()->unique()->sentence(3),
            'nomor_dokumen' => fake()->unique()->bothify('DOC-###'),
            'submitted_at' => now(),
        ]);

        $document->departments()->sync(collect($departments)->pluck('id')->all());

        return $document;
    }
}
