<?php

namespace App\Livewire\Administration\AccessMenu;

use App\Models\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Menu Akses')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $module = '';

    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingModule(): void
    {
        $this->resetPage();
    }

    public function getPermissionsProperty(): LengthAwarePaginator
    {
        return Permission::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%')
                        ->orWhere('route', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->module !== '', fn ($query) => $query->where('module', $this->module))
            ->orderBy('module')
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        $modules = Permission::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module', 'module')
            ->prepend('Semua Module', '')
            ->all();

        return view('livewire.administration.access-menus.index', [
            'permissions' => $this->permissions,
            'moduleOptions' => $modules,
        ]);
    }
}
