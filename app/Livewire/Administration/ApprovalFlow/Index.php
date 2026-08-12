<?php

namespace App\Livewire\Administration\ApprovalFlow;

use App\Actions\Administration\ApprovalFlow\CreateApprovalFlowStage;
use App\Actions\Administration\ApprovalFlow\DeleteApprovalFlowStage;
use App\Actions\Administration\ApprovalFlow\EnsureApprovalFlow;
use App\Actions\Administration\ApprovalFlow\UpdateApprovalFlowStage;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\DocumentType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Approval Flow')]
class Index extends Component
{
    public ?int $selectedDocumentTypeId = null;

    public ?int $approvalFlowId = null;

    public ?int $editingStageId = null;

    public ?int $deletingStageId = null;

    public string $keterangan = '';

    public string $nama_tahap = '';

    public bool $showStageForm = false;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->selectedDocumentTypeId = DocumentType::query()
            ->active()
            ->orderBy('nama_types')
            ->value('id');

        if ($this->selectedDocumentTypeId !== null) {
            $this->loadApprovalFlow();
        }
    }

    public function selectDocumentType(int $documentTypeId): void
    {
        $this->selectedDocumentTypeId = $documentTypeId;
        $this->resetStageForm();
        $this->cancelDelete();
        $this->loadApprovalFlow();
    }

    public function createStage(): void
    {
        $this->resetStageForm();
        $this->showStageForm = true;
    }

    public function editStage(int $stageId): void
    {
        $stage = ApprovalFlowStage::query()
            ->whereHas('approvalFlow', function ($query): void {
                $query->where('id', $this->approvalFlowId);
            })
            ->findOrFail($stageId);

        $this->editingStageId = $stage->id;
        $this->keterangan = $stage->keterangan ?? '';
        $this->nama_tahap = $stage->nama_tahap;
        $this->showStageForm = true;
    }

    /**
     * @throws ValidationException
     */
    public function saveStage(
        CreateApprovalFlowStage $createApprovalFlowStage,
        UpdateApprovalFlowStage $updateApprovalFlowStage,
    ): void {
        $approvalFlow = $this->approvalFlow();

        $data = [
            'keterangan' => $this->keterangan,
            'nama_tahap' => $this->nama_tahap,
        ];

        if ($this->editingStageId !== null) {
            $stage = $approvalFlow->stages()->findOrFail($this->editingStageId);

            $updateApprovalFlowStage->handle($stage, $data);
        } else {
            $createApprovalFlowStage->handle($approvalFlow, $data);
        }

        $this->resetStageForm();
    }

    public function confirmDeleteStage(int $stageId): void
    {
        $this->deletingStageId = $stageId;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingStageId = null;
        $this->showDeleteModal = false;
    }

    public function deleteStage(DeleteApprovalFlowStage $deleteApprovalFlowStage): void
    {
        if ($this->deletingStageId === null) {
            return;
        }

        $stage = $this->approvalFlow()
            ->stages()
            ->findOrFail($this->deletingStageId);

        $deleteApprovalFlowStage->handle($stage);

        $this->cancelDelete();
    }

    public function cancelStageForm(): void
    {
        $this->resetStageForm();
    }

    public function getDocumentTypesProperty(): Collection
    {
        return DocumentType::query()
            ->active()
            ->orderBy('nama_types')
            ->get();
    }

    public function getSelectedDocumentTypeProperty(): ?DocumentType
    {
        if ($this->selectedDocumentTypeId === null) {
            return null;
        }

        return $this->documentTypes->firstWhere('id', $this->selectedDocumentTypeId);
    }

    public function getApprovalStagesProperty(): Collection
    {
        if ($this->approvalFlowId === null) {
            return new Collection;
        }

        return ApprovalFlowStage::query()
            ->where('m_approval_flow_id', $this->approvalFlowId)
            ->orderBy('stage_order')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.administration.approval-flows.index', [
            'documentTypes' => $this->documentTypes,
            'selectedDocumentType' => $this->selectedDocumentType,
            'approvalStages' => $this->approvalStages,
        ]);
    }

    protected function loadApprovalFlow(): void
    {
        $this->approvalFlowId = $this->approvalFlow()->id;
    }

    protected function approvalFlow(): ApprovalFlow
    {
        return app(EnsureApprovalFlow::class)->handle([
            'm_document_types_id' => $this->selectedDocumentTypeId,
        ]);
    }

    protected function resetStageForm(): void
    {
        $this->reset([
            'editingStageId',
            'keterangan',
            'nama_tahap',
            'showStageForm',
        ]);
    }
}
