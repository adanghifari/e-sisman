<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentType;
use App\Models\ImportedExistingDocument;
use App\Models\ImportedExistingDocumentFile;
use App\Models\ImportedExistingDocumentRelation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportedExistingDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_existing_pages_render(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = ImportedExistingDocument::create([
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Render',
            'nomor_dokumen' => 'LEG-RENDER',
            'nomor_revisi' => '00.01',
        ]);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.index'))
            ->assertOk()
            ->assertSee('Arsip Dokumen Existing')
            ->assertSee('Legacy Render');

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.create'))
            ->assertOk()
            ->assertSee('Tambah Arsip Dokumen Existing')
            ->assertSee('Sesuai Ketentuan Saat Ini');

        $this->actingAs($user)
            ->get(route('documents.existing.imports.show', $document))
            ->assertOk()
            ->assertSee('Detail Arsip Dokumen Existing')
            ->assertSee('LEG-RENDER')
            ->assertSee('00.01');
    }

    public function test_imported_existing_obsolete_can_be_stored_with_nullable_modern_master_data(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        ImportedExistingDocument::create([
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Duplicate Sebelumnya',
            'nomor_dokumen' => 'LEG-SAME-001',
        ]);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
                'nama_dokumen' => 'Instruksi Legacy Obsolete',
                'nomor_dokumen' => 'LEG-SAME-001',
                'nomor_revisi' => 'Rev A',
                'tanggal_terbit' => '2020-01-10',
                'tanggal_obsolete' => '2024-04-05',
                'catatan' => 'Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.',
                'obsolete_document' => UploadedFile::fake()->create('legacy-obsolete.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = ImportedExistingDocument::query()
            ->where('nama_dokumen', 'Instruksi Legacy Obsolete')
            ->firstOrFail();

        $this->assertSame(ImportedExistingDocument::LEGACY_RULE, $document->obsolete_rule_type);
        $this->assertNull($document->m_document_level_id);
        $this->assertNull($document->m_document_types_id);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertSame('LEG-SAME-001', $document->nomor_dokumen);
        $this->assertSame('Rev A', $document->nomor_revisi);
        $this->assertSame('Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.', $document->catatan);
        $this->assertSame(2, ImportedExistingDocument::query()->where('nomor_dokumen', 'LEG-SAME-001')->count());

        $file = $document->files()->firstOrFail();

        $this->assertSame(ImportedExistingDocumentFile::OBSOLETE_DOCUMENT, $file->type_file);
        Storage::disk('local')->assertExists($file->path_file);
    }

    public function test_imported_existing_document_can_store_relations_to_imported_and_t_document(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = ImportedExistingDocument::create([
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Target',
            'nomor_dokumen' => 'LEG-TARGET',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Source',
                'nomor_revisi' => '00.01',
                'obsolete_document' => UploadedFile::fake()->create('legacy-source.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_existing_document_id' => $targetImported->id,
                        'relation_type' => ImportedExistingDocumentRelation::SUPERSEDED_BY,
                        'keterangan' => 'Digantikan arsip legacy berikutnya.',
                    ],
                    [
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => ImportedExistingDocumentRelation::REFERENCES,
                    ],
                ],
            ])
            ->assertRedirect();

        $source = ImportedExistingDocument::query()
            ->where('nama_dokumen', 'Legacy Source')
            ->firstOrFail();

        $this->assertSame(2, $source->outgoingRelations()->count());
        $this->assertDatabaseHas('imported_existing_document_relations', [
            'imported_existing_document_id' => $source->id,
            'related_imported_existing_document_id' => $targetImported->id,
            'related_document_id' => null,
            'relation_type' => ImportedExistingDocumentRelation::SUPERSEDED_BY,
        ]);
        $this->assertDatabaseHas('imported_existing_document_relations', [
            'imported_existing_document_id' => $source->id,
            'related_imported_existing_document_id' => null,
            'related_document_id' => $targetDocument->id,
            'relation_type' => ImportedExistingDocumentRelation::REFERENCES,
        ]);
    }

    public function test_imported_existing_master_can_be_stored_with_current_rule_and_claimed_number(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Sebelum Go Live',
                'nomor_dokumen' => 'PS-SMR-120',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = ImportedExistingDocument::query()
            ->where('nama_dokumen', 'Existing Master Sebelum Go Live')
            ->firstOrFail();

        $this->assertSame(ImportedExistingDocument::STATE_MASTER, $document->document_state);
        $this->assertSame(ImportedExistingDocument::CURRENT_RULE, $document->obsolete_rule_type);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'PS-SMR-120',
            'source_type' => DocumentNumberRegistry::SOURCE_IMPORTED_EXISTING,
            'source_id' => $document->id,
        ]);
    }

    public function test_imported_existing_master_revision_number_must_use_two_digit_dot_format(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Format Revisi Salah',
                'nomor_dokumen' => 'PS-SMR-121',
                'nomor_revisi' => '00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_revisi');

        $this->assertFalse(ImportedExistingDocument::query()->where('nama_dokumen', 'Existing Master Format Revisi Salah')->exists());
    }

    public function test_numbering_setup_blocks_v2_document_number_inside_reserved_range(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.create.create',
        ]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);

        DocumentNumberingSetup::create([
            'scope_identifier' => 'PS-SMR',
            'existing_start_number' => 1,
            'existing_end_number' => 127,
            'v2_start_number' => 128,
            'configured_by' => $user->id,
            'configured_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Nomor Reserved',
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 100, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_dokumen_suffix');

        $this->assertFalse(Document::query()->where('nomor_dokumen', 'PS-SMR-001')->exists());
    }

    public function test_late_import_conflicting_with_v2_number_is_rejected_without_auto_renumber(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'V2 Sudah Pakai Nomor',
            'nomor_dokumen' => 'PS-SMR-128',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Late Import Bentrok',
                'nomor_dokumen' => 'PS-SMR-128',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('late-import.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_dokumen');

        $this->assertFalse(ImportedExistingDocument::query()->where('nama_dokumen', 'Late Import Bentrok')->exists());
    }

    public function test_imported_existing_master_revision_bridge_creates_t_document_and_obsoletes_source_after_approval(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
            'documents.approval.approve',
            'documents.approval.show',
            'documents.master.view',
        ]);
        $proposedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $approvedStatus = ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);
        $pendingStatus = ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::PENDING], ['nama_status' => 'Menunggu']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::WAITING], ['nama_status' => 'Menunggu Giliran']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::REJECTED], ['nama_status' => 'Ditolak']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::TERMINATED], ['nama_status' => 'Dihentikan']);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);

        $source = ImportedExistingDocument::create([
            'document_state' => ImportedExistingDocument::STATE_MASTER,
            'obsolete_rule_type' => ImportedExistingDocument::CURRENT_RULE,
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Imported Master Source',
            'nomor_dokumen' => 'PS-SMR-120',
            'nomor_revisi' => '00.01',
        ]);
        $source->departments()->sync([$department->id]);
        $olderObsolete = ImportedExistingDocument::create([
            'document_state' => ImportedExistingDocument::STATE_OBSOLETE,
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Imported Master Source Versi Lama',
            'nomor_dokumen' => 'PS-SMR-120-OLD',
            'nomor_revisi' => '00.00',
            'tanggal_obsolete' => now()->subDay()->toDateString(),
        ]);
        ImportedExistingDocumentRelation::create([
            'imported_existing_document_id' => $olderObsolete->id,
            'related_imported_existing_document_id' => $source->id,
            'relation_type' => ImportedExistingDocumentRelation::SUPERSEDED_BY,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('documents.existing.imports.revisions.store', $source), [
                'nama_dokumen' => 'Imported Master Source Rev 1',
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 100, 'application/pdf'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect();

        $revision = Document::query()
            ->where('imported_existing_source_id', $source->id)
            ->firstOrFail();

        $this->assertSame($proposedStatus->id, $revision->m_status_document_id);
        $this->assertNull($revision->revised_from);
        $this->assertSame('revision', $revision->request_type);
        $this->assertSame(2, $revision->nomor_revisi);
        $this->assertSame('00.02', $revision->formatted_revision);
        $this->assertTrue($revision->departments()->whereKey($department->id)->exists());

        $revision->approvals()->create([
            'm_approval_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'role_id' => null,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'stages' => 'Approval Imported Existing',
        ]);

        $approvalResponse = $this->actingAs($user)
            ->post(route('documents.approval.approve', $revision));

        $approvalResponse->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $source->refresh();

        $this->assertSame(StatusDocument::APPROVED, $revision->status->nama_status);
        $this->assertSame('PS-SMR-120', $revision->nomor_dokumen);
        $this->assertSame(ImportedExistingDocument::STATE_OBSOLETE, $source->document_state);
        $this->assertDatabaseHas('imported_existing_document_relations', [
            'imported_existing_document_id' => $source->id,
            'related_imported_existing_document_id' => null,
            'related_document_id' => $revision->id,
            'relation_type' => ImportedExistingDocumentRelation::SUPERSEDED_BY,
        ]);
        $this->assertSame($approvedStatus->id, $revision->approvals()->first()->m_approval_status_id);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Imported Master Source Rev 1')
            ->assertSee('PS-SMR-120-OLD')
            ->assertSee('Imported Master Source Versi Lama');
    }

    public function test_current_rule_requires_all_modern_master_data(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedExistingDocument::CURRENT_RULE,
                'nama_dokumen' => 'Current Rule Tanpa Master Data',
                'obsolete_document' => UploadedFile::fake()->create('current-rule.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors([
                'm_document_level_id',
                'm_document_types_id',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
            ]);

        $this->assertFalse(ImportedExistingDocument::query()->where('nama_dokumen', 'Current Rule Tanpa Master Data')->exists());
    }

    public function test_imported_existing_relation_requires_exactly_one_target(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = ImportedExistingDocument::create([
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Target',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid No Target',
                'obsolete_document' => UploadedFile::fake()->create('legacy-no-target.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'relation_type' => ImportedExistingDocumentRelation::RELATED_TO,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_existing_document_id');

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid Two Targets',
                'obsolete_document' => UploadedFile::fake()->create('legacy-two-targets.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_existing_document_id' => $targetImported->id,
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => ImportedExistingDocumentRelation::RELATED_TO,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_existing_document_id');

        $this->assertFalse(ImportedExistingDocument::query()->where('nama_dokumen', 'Legacy Invalid No Target')->exists());
        $this->assertFalse(ImportedExistingDocument::query()->where('nama_dokumen', 'Legacy Invalid Two Targets')->exists());
    }

    public function test_imported_existing_preview_only_accepts_pdf_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = ImportedExistingDocument::create([
            'obsolete_rule_type' => ImportedExistingDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Preview',
        ]);
        Storage::disk('local')->put('documents/imported-existing/preview.pdf', "%PDF-1.4\nfixture");
        Storage::disk('local')->put('documents/imported-existing/preview.docx', 'doc fixture');
        $pdfFile = $document->files()->create([
            'type_file' => ImportedExistingDocumentFile::OBSOLETE_DOCUMENT,
            'path_file' => 'documents/imported-existing/preview.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.pdf',
            'stored_file_name' => 'preview.pdf',
            'file_size' => 16,
        ]);
        $wordFile = $document->files()->create([
            'type_file' => ImportedExistingDocumentFile::ATTACHMENT,
            'path_file' => 'documents/imported-existing/preview.docx',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.docx',
            'stored_file_name' => 'preview.docx',
            'file_size' => 11,
        ]);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.files.preview', [$document, $pdfFile]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('documents.existing.imports.files.preview', [$document, $wordFile]))
            ->assertStatus(415);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role Imported Existing']);
        $user = User::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => $permissionCode,
                    'module' => 'Manajemen Dokumen',
                    'route' => match ($permissionCode) {
                        'documents.master.imports.create' => 'documents.master.imports.create',
                        'documents.master.imports.create-level' => 'documents.master.imports.create.level',
                        'documents.master.imports.store' => 'documents.master.imports.store',
                        'documents.master.imports.store-level' => 'documents.master.imports.store.level',
                        'documents.obsolete.imports.create' => 'documents.obsolete.imports.create',
                        'documents.obsolete.imports.store' => 'documents.obsolete.imports.store',
                        'documents.existing.imports.revision' => 'documents.existing.imports.revisions.store',
                        'documents.existing.imports.numbering-setup' => 'documents.existing.imports.numbering-setups.store',
                        'documents.create.create' => 'documents.store',
                        'documents.master.view' => 'documents.master',
                        'documents.approval.approve' => 'documents.approval.approve',
                        'documents.approval.show' => 'documents.approval.show',
                        'documents.existing.imports.detail' => 'documents.existing.imports.show',
                        'documents.existing.imports.download' => 'documents.existing.imports.files.show',
                        'documents.existing.imports.preview' => 'documents.existing.imports.files.preview',
                        default => 'documents.existing.imports.index',
                    },
                    'action' => str_contains($permissionCode, '.create') || str_contains($permissionCode, '.store')
                        ? 'create'
                        : 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role);

        return $user->refresh();
    }

    private function existingMasterFixture(array $permissionCodes): array
    {
        $user = $this->userWithPermissions($permissionCodes);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        return [$user, $level, $documentType, $businessProcess, $businessFunction, $department];
    }

    private function createExistingDocument(User $user): Document
    {
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        return Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Master Aktif Saat Ini',
            'nomor_dokumen' => 'PS-SMR-ACTIVE',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
    }
}
