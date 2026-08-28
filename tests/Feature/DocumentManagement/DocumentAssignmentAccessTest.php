<?php

namespace Tests\Feature\DocumentManagement;

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
