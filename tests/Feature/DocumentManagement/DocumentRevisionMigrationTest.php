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
use Tests\TestCase;

class DocumentRevisionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_obsoletes_previous_approved_revision_records(): void
    {
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $user = User::factory()->create();
        $documentType = DocumentType::create(['nama_types' => 'IK']);
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
            'kode_department' => 'ITSM',
            'nama_department' => 'IT & Management System Department',
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Dokumen Lama',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDay(),
        ]);
        $source->departments()->sync([$department->id]);

        $revision = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'nama_dokumen' => 'Dokumen Revisi',
            'nomor_dokumen' => 'FMIK-SMR-010',
            'nomor_revisi' => 1,
            'approved_at' => now(),
        ]);
        $revision->departments()->sync([$department->id]);

        $migration = require database_path('migrations/2026_08_18_050000_obsolete_previous_approved_document_revisions.php');
        $migration->up();

        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $revision->refresh()->status->nama_status);
    }

    public function test_revision_number_migration_backfills_revision_form_number_and_restores_logical_document_number(): void
    {
        $migration = require database_path('migrations/2026_08_22_000001_add_nomor_lembar_revisi_to_documents.php');
        $migration->down();

        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $user = User::factory()->create();
        $documentType = DocumentType::create(['nama_types' => 'IK']);
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Instruksi Kerja Lama',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
        ]);

        $revision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Instruksi Kerja Revisi',
            'nomor_dokumen' => 'FMIK-SMR-010',
            'nomor_revisi' => 1,
        ]);

        $migration->up();

        $revision->refresh();

        $this->assertSame('IK-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMIK-SMR-010-01', $revision->nomor_lembar_revisi);
        $this->assertNull($source->refresh()->nomor_lembar_revisi);
    }

    public function test_revision_number_migration_backfills_legacy_revision_with_null_request_type(): void
    {
        $migration = require database_path('migrations/2026_08_22_000001_add_nomor_lembar_revisi_to_documents.php');
        $migration->down();

        [$source, $user, $businessProcess, $businessFunction, $level] = $this->legacyRevisionFixture();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();

        $revision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'request_type' => null,
            'nama_dokumen' => 'Prosedur Revisi Legacy',
            'nomor_dokumen' => 'FMPS-SMR-010',
            'nomor_revisi' => 1,
        ]);

        $migration->up();

        $revision->refresh();

        $this->assertSame($level->id, $source->m_document_level_id);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
    }

    public function test_revision_number_migration_backfills_multi_generation_legacy_chain(): void
    {
        $migration = require database_path('migrations/2026_08_22_000001_add_nomor_lembar_revisi_to_documents.php');
        $migration->down();

        [$source, $user, $businessProcess, $businessFunction] = $this->legacyRevisionFixture();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();

        $revisionOne = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'request_type' => null,
            'nama_dokumen' => 'Prosedur Revisi Legacy 1',
            'nomor_dokumen' => 'FMPS-SMR-010',
            'nomor_revisi' => 1,
        ]);
        $revisionTwo = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $revisionOne->id,
            'request_type' => null,
            'nama_dokumen' => 'Prosedur Revisi Legacy 2',
            'nomor_dokumen' => 'FMPS-SMR-010',
            'nomor_revisi' => 2,
        ]);

        $migration->up();

        $revisionOne->refresh();
        $revisionTwo->refresh();

        $this->assertSame('PS-SMR-010', $revisionOne->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revisionOne->nomor_lembar_revisi);
        $this->assertSame('PS-SMR-010', $revisionTwo->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-02', $revisionTwo->nomor_lembar_revisi);
    }

    private function legacyRevisionFixture(): array
    {
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $user = User::factory()->create();
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
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
        ]);

        return [$source, $user, $businessProcess, $businessFunction, $level];
    }
}
