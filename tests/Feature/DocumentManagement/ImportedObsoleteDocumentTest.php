<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\ImportedObsoleteDocument;
use App\Models\ImportedObsoleteDocumentFile;
use App\Models\ImportedObsoleteDocumentRelation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportedObsoleteDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_obsolete_pages_render(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = ImportedObsoleteDocument::create([
            'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Render',
            'nomor_dokumen' => 'LEG-RENDER',
            'nomor_revisi' => '00.01',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.index'))
            ->assertOk()
            ->assertSee('Arsip Dokumen Obsolete Legacy')
            ->assertSee('Legacy Render');

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.create'))
            ->assertOk()
            ->assertSee('Tambah Arsip Dokumen Obsolete Legacy')
            ->assertSee('Sesuai Ketentuan Saat Ini');

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.show', $document))
            ->assertOk()
            ->assertSee('Detail Arsip Dokumen Obsolete Legacy')
            ->assertSee('LEG-RENDER')
            ->assertSee('00.01');
    }

    public function test_imported_obsolete_document_can_be_stored_with_nullable_modern_master_data(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        ImportedObsoleteDocument::create([
            'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Duplicate Sebelumnya',
            'nomor_dokumen' => 'LEG-SAME-001',
        ]);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
                'nama_dokumen' => 'Instruksi Legacy Obsolete',
                'nomor_dokumen' => 'LEG-SAME-001',
                'nomor_revisi' => 'Rev A',
                'tanggal_terbit' => '2020-01-10',
                'tanggal_obsolete' => '2024-04-05',
                'catatan' => 'Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.',
                'obsolete_document' => UploadedFile::fake()->create('legacy-obsolete.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = ImportedObsoleteDocument::query()
            ->where('nama_dokumen', 'Instruksi Legacy Obsolete')
            ->firstOrFail();

        $this->assertSame(ImportedObsoleteDocument::LEGACY_RULE, $document->obsolete_rule_type);
        $this->assertNull($document->m_document_level_id);
        $this->assertNull($document->m_document_types_id);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertSame('LEG-SAME-001', $document->nomor_dokumen);
        $this->assertSame('Rev A', $document->nomor_revisi);
        $this->assertSame('Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.', $document->catatan);
        $this->assertSame(2, ImportedObsoleteDocument::query()->where('nomor_dokumen', 'LEG-SAME-001')->count());

        $file = $document->files()->firstOrFail();

        $this->assertSame(ImportedObsoleteDocumentFile::OBSOLETE_DOCUMENT, $file->type_file);
        Storage::disk('local')->assertExists($file->path_file);
    }

    public function test_imported_obsolete_document_can_store_relations_to_imported_and_existing_documents(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = ImportedObsoleteDocument::create([
            'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Target',
            'nomor_dokumen' => 'LEG-TARGET',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedObsoleteDocument::CURRENT_RULE,
                'nama_dokumen' => 'Legacy Source',
                'nomor_revisi' => '00.01',
                'obsolete_document' => UploadedFile::fake()->create('legacy-source.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_obsolete_document_id' => $targetImported->id,
                        'relation_type' => ImportedObsoleteDocumentRelation::SUPERSEDED_BY,
                        'keterangan' => 'Digantikan arsip legacy berikutnya.',
                    ],
                    [
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => ImportedObsoleteDocumentRelation::REFERENCES,
                    ],
                ],
            ])
            ->assertRedirect();

        $source = ImportedObsoleteDocument::query()
            ->where('nama_dokumen', 'Legacy Source')
            ->firstOrFail();

        $this->assertSame(2, $source->outgoingRelations()->count());
        $this->assertDatabaseHas('imported_obsolete_document_relations', [
            'imported_obsolete_document_id' => $source->id,
            'related_imported_obsolete_document_id' => $targetImported->id,
            'related_document_id' => null,
            'relation_type' => ImportedObsoleteDocumentRelation::SUPERSEDED_BY,
        ]);
        $this->assertDatabaseHas('imported_obsolete_document_relations', [
            'imported_obsolete_document_id' => $source->id,
            'related_imported_obsolete_document_id' => null,
            'related_document_id' => $targetDocument->id,
            'relation_type' => ImportedObsoleteDocumentRelation::REFERENCES,
        ]);
    }

    public function test_imported_obsolete_relation_requires_exactly_one_target(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = ImportedObsoleteDocument::create([
            'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Target',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid No Target',
                'obsolete_document' => UploadedFile::fake()->create('legacy-no-target.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'relation_type' => ImportedObsoleteDocumentRelation::RELATED_TO,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_obsolete_document_id');

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid Two Targets',
                'obsolete_document' => UploadedFile::fake()->create('legacy-two-targets.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_obsolete_document_id' => $targetImported->id,
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => ImportedObsoleteDocumentRelation::RELATED_TO,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_obsolete_document_id');

        $this->assertFalse(ImportedObsoleteDocument::query()->where('nama_dokumen', 'Legacy Invalid No Target')->exists());
        $this->assertFalse(ImportedObsoleteDocument::query()->where('nama_dokumen', 'Legacy Invalid Two Targets')->exists());
    }

    public function test_imported_obsolete_preview_only_accepts_pdf_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = ImportedObsoleteDocument::create([
            'obsolete_rule_type' => ImportedObsoleteDocument::LEGACY_RULE,
            'uploaded_by' => $user->id,
            'nama_dokumen' => 'Legacy Preview',
        ]);
        Storage::disk('local')->put('documents/imported-obsolete/preview.pdf', "%PDF-1.4\nfixture");
        Storage::disk('local')->put('documents/imported-obsolete/preview.docx', 'doc fixture');
        $pdfFile = $document->files()->create([
            'type_file' => ImportedObsoleteDocumentFile::OBSOLETE_DOCUMENT,
            'path_file' => 'documents/imported-obsolete/preview.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.pdf',
            'stored_file_name' => 'preview.pdf',
            'file_size' => 16,
        ]);
        $wordFile = $document->files()->create([
            'type_file' => ImportedObsoleteDocumentFile::ATTACHMENT,
            'path_file' => 'documents/imported-obsolete/preview.docx',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.docx',
            'stored_file_name' => 'preview.docx',
            'file_size' => 11,
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.files.preview', [$document, $pdfFile]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.files.preview', [$document, $wordFile]))
            ->assertStatus(415);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role Imported Obsolete']);
        $user = User::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => $permissionCode,
                    'module' => 'Manajemen Dokumen',
                    'route' => match ($permissionCode) {
                        'documents.obsolete.imports.create' => 'documents.obsolete.imports.create',
                        'documents.obsolete.imports.store' => 'documents.obsolete.imports.store',
                        'documents.obsolete.imports.detail' => 'documents.obsolete.imports.show',
                        'documents.obsolete.imports.download' => 'documents.obsolete.imports.files.show',
                        'documents.obsolete.imports.preview' => 'documents.obsolete.imports.files.preview',
                        default => 'documents.obsolete.imports.index',
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
