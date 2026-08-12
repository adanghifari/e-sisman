<?php

namespace Tests\Feature\Administration;

use App\Livewire\Administration\ApprovalFlow\Index;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\DocumentLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_flow_page_uses_document_levels(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('approval-flows.index'))
            ->assertOk()
            ->assertSee('Level I')
            ->assertSee('Level II')
            ->assertSee('Level III')
            ->assertDontSee('Pedoman');
    }

    public function test_stage_can_be_created_for_selected_document_level(): void
    {
        $this->actingAs(User::factory()->create());

        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        Livewire::test(Index::class)
            ->call('selectDocumentLevel', $level->id)
            ->call('createStage')
            ->set('keterangan', 'Diperiksa oleh')
            ->set('nama_tahap', 'Manager')
            ->call('saveStage')
            ->assertHasNoErrors();

        $flow = ApprovalFlow::query()
            ->where('m_document_level_id', $level->id)
            ->firstOrFail();

        $this->assertDatabaseHas('m_approval_flow_stages', [
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
    }

    public function test_stage_can_be_updated(): void
    {
        $this->actingAs(User::factory()->create());

        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();
        $flow = ApprovalFlow::query()->firstOrCreate(
            ['m_document_level_id' => $level->id],
            ['nama_flow' => 'Flow Level I'],
        );
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);

        Livewire::test(Index::class)
            ->call('selectDocumentLevel', $level->id)
            ->call('editStage', $stage->id)
            ->set('keterangan', 'Disahkan oleh')
            ->set('nama_tahap', 'Direktur')
            ->call('saveStage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('m_approval_flow_stages', [
            'id' => $stage->id,
            'keterangan' => 'Disahkan oleh',
            'nama_tahap' => 'Direktur',
        ]);
    }

    public function test_stage_can_be_deleted_and_remaining_stages_are_reordered(): void
    {
        $this->actingAs(User::factory()->create());

        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $flow = ApprovalFlow::query()->firstOrCreate(
            ['m_document_level_id' => $level->id],
            ['nama_flow' => 'Flow Level III'],
        );
        $firstStage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Dibuat oleh',
            'nama_tahap' => 'Staff',
        ]);
        $secondStage = $flow->stages()->create([
            'stage_order' => 2,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Manager',
        ]);
        $thirdStage = $flow->stages()->create([
            'stage_order' => 3,
            'keterangan' => 'Disetujui oleh',
            'nama_tahap' => 'Kepala Departemen',
        ]);

        Livewire::test(Index::class)
            ->call('selectDocumentLevel', $level->id)
            ->call('confirmDeleteStage', $secondStage->id)
            ->call('deleteStage');

        $this->assertDatabaseMissing('m_approval_flow_stages', [
            'id' => $secondStage->id,
        ]);

        $this->assertSame(1, $firstStage->refresh()->stage_order);
        $this->assertSame(2, $thirdStage->refresh()->stage_order);
    }

    public function test_stage_requires_approval_party(): void
    {
        $this->actingAs(User::factory()->create());

        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();

        Livewire::test(Index::class)
            ->call('selectDocumentLevel', $level->id)
            ->call('createStage')
            ->set('keterangan', 'Dibuat oleh')
            ->set('nama_tahap', '')
            ->call('saveStage')
            ->assertHasErrors(['nama_tahap']);
    }
}
