<?php

namespace App\Livewire\MasterData\BusinessProcess;

use App\Actions\MasterData\BusinessProcess\CreateBusinessProcess;
use App\Actions\MasterData\BusinessProcess\DeleteBusinessProcess;
use App\Actions\MasterData\BusinessProcess\ToggleBusinessProcessStatus;
use App\Actions\MasterData\BusinessProcess\UpdateBusinessProcess;
use App\Models\BusinessProcess;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Proses Bisnis')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public int $perPage = 10;

    public ?int $editingId = null;

    public bool $showForm = false;

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $kode = '';

    public string $nama_proses_bisnis = '';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();

        $this->showForm = true;
        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $businessProcess = BusinessProcess::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $businessProcess->id;
        $this->kode = $businessProcess->kode;
        $this->nama_proses_bisnis = $businessProcess->nama_proses_bisnis;
        $this->is_active = $businessProcess->is_active;
    }

    /**
     * @throws ValidationException
     */
    public function save(
        CreateBusinessProcess $createBusinessProcess,
        UpdateBusinessProcess $updateBusinessProcess,
    ): void {
        $data = [
            'kode' => $this->kode,
            'nama_proses_bisnis' => $this->nama_proses_bisnis,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $businessProcess = BusinessProcess::findOrFail($this->editingId);

            $updateBusinessProcess->handle($businessProcess, $data);
        } else {
            $createBusinessProcess->handle($data);
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function toggleStatus(
        int $id,
        ToggleBusinessProcessStatus $toggleBusinessProcessStatus,
    ): void {
        $businessProcess = BusinessProcess::findOrFail($id);

        $toggleBusinessProcessStatus->handle($businessProcess);
    }

    public function confirmDelete(int $id): void
    {
        BusinessProcess::findOrFail($id);

        $this->resetErrorBag('delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    /**
     * @throws ValidationException
     */
    public function delete(DeleteBusinessProcess $deleteBusinessProcess): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $businessProcess = BusinessProcess::findOrFail($this->deletingId);

        $deleteBusinessProcess->handle($businessProcess);

        $this->cancelDelete();
        $this->resetPage();
    }

    public function cancelDelete(): void
    {
        $this->resetErrorBag('delete');
        $this->reset([
            'deletingId',
            'showDeleteModal',
        ]);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function getBusinessProcessesProperty(): LengthAwarePaginator
    {
        return BusinessProcess::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('kode', 'like', '%'.$this->search.'%')
                        ->orWhere('nama_proses_bisnis', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('nama_proses_bisnis')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.master-data.business-process.index', [
            'businessProcesses' => $this->businessProcesses,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'showForm',
            'kode',
            'nama_proses_bisnis',
        ]);

        $this->is_active = true;
    }
}
