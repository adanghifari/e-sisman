<?php

namespace App\Livewire\Administration\User;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('User')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $role = '';

    public string $department = '';

    public string $status = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingDepartment(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getUsersProperty(): LengthAwarePaginator
    {
        return User::query()
            ->with(['department', 'roles'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('nik', 'like', '%'.$this->search.'%')
                        ->orWhere('jabatan', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->role !== '', function ($query): void {
                $query->whereHas('roles', fn ($query) => $query->whereKey($this->role));
            })
            ->when($this->department !== '', function ($query): void {
                $query->where('m_department_id', $this->department);
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function getRoleOptionsProperty(): array
    {
        return ['' => 'Semua Role'] + Role::query()
            ->orderBy('nama_role')
            ->pluck('nama_role', 'id')
            ->all();
    }

    public function getDepartmentOptionsProperty(): array
    {
        return ['' => 'Semua Department'] + Department::query()
            ->orderBy('nama_department')
            ->pluck('nama_department', 'id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.administration.users.index', [
            'users' => $this->users,
            'roleOptions' => $this->roleOptions,
            'departmentOptions' => $this->departmentOptions,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
            ],
        ]);
    }
}
