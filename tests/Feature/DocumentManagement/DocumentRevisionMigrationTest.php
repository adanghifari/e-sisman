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
}
