<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAutosaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_creates_and_updates_draft_for_same_document_number(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->autosaveFixture();

        $response = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft Autosave Prosedur',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '77',
                'catatan_revisi' => 'Catatan pertama',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $draftId = $response->json('draft_id');
        $draft = Document::query()->findOrFail($draftId);

        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertSame('PS-SMR-77', $draft->nomor_dokumen);
        $this->assertSame('Catatan pertama', $draft->catatan_revisi);

        $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'draft_id' => $draftId,
                'nama_dokumen' => 'Draft Autosave Prosedur Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '77',
                'catatan_revisi' => 'Catatan kedua',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'draft_id' => $draftId,
            ]);

        $this->assertSame(1, Document::query()->where('user_id', $user->id)->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))->count());
        $this->assertSame('Catatan kedua', $draft->refresh()->catatan_revisi);
        $this->assertSame('Draft Autosave Prosedur Updated', $draft->nama_dokumen);
    }

    public function test_autosave_creates_new_draft_when_document_number_changes(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->autosaveFixture();

        $firstResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft Nomor Pertama',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '81',
            ])
            ->assertOk();

        $secondResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'draft_id' => $firstResponse->json('draft_id'),
                'nama_dokumen' => 'Draft Nomor Kedua',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '82',
            ])
            ->assertOk();

        $this->assertNotSame($firstResponse->json('draft_id'), $secondResponse->json('draft_id'));
        $this->assertDatabaseHas('t_document', [
            'id' => $firstResponse->json('draft_id'),
            'nomor_dokumen' => 'PS-SMR-81',
        ]);
        $this->assertDatabaseHas('t_document', [
            'id' => $secondResponse->json('draft_id'),
            'nomor_dokumen' => 'PS-SMR-82',
        ]);
    }

    private function autosaveFixture(): array
    {
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $user = User::factory()->create();
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

        return [$user, $businessProcess, $businessFunction, $department];
    }
}
