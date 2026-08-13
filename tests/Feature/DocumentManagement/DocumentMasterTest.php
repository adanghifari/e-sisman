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
