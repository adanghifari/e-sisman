<?php

namespace App\Livewire\MasterData\Department;

use App\Actions\MasterData\Department\CreateDepartment;
use App\Actions\MasterData\Department\DeleteDepartment;
use App\Actions\MasterData\Department\ToggleDepartmentStatus;
use App\Actions\MasterData\Department\UpdateDepartment;
use App\Livewire\MasterData\Concerns\HandlesMasterDataCrudState;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Department')]
class Index extends Component
{
    use HandlesMasterDataCrudState;
    use WithPagination;

    public string $kode_department = '';

    public string $nama_department = '';

    public function edit(int $id): void
    {
        $department = Department::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $department->id;
        $this->kode_department = $department->kode_department;
        $this->nama_department = $department->nama_department;
        $this->is_active = $department->is_active;
    }

    /**
     * @throws ValidationException
     */
    public function save(
        CreateDepartment $createDepartment,
        UpdateDepartment $updateDepartment,
    ): void {
        $data = [
            'kode_department' => $this->kode_department,
            'nama_department' => $this->nama_department,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $department = Department::findOrFail($this->editingId);

            $updateDepartment->handle($department, $data);
        } else {
            $createDepartment->handle($data);
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function toggleStatus(
        int $id,
        ToggleDepartmentStatus $toggleDepartmentStatus,
    ): void {
        $department = Department::findOrFail($id);

        $toggleDepartmentStatus->handle($department);
    }

    /**
     * @throws ValidationException
     */
    public function delete(DeleteDepartment $deleteDepartment): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $department = Department::findOrFail($this->deletingId);

        $deleteDepartment->handle($department);

        $this->cancelDelete();
        $this->resetPage();
    }

    public function getDepartmentsProperty(): LengthAwarePaginator
    {
        return Department::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('kode_department', 'like', '%'.$this->search.'%')
                        ->orWhere('nama_department', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('nama_department')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.master-data.department.index', [
            'departments' => $this->departments,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ]);
    }

    protected function masterDataModelClass(): string
    {
        return Department::class;
    }

    protected function resetForm(): void
    {
        $this->resetMasterDataForm([
            'kode_department',
            'nama_department',
        ]);
    }
}
