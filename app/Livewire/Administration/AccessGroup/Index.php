<?php

namespace App\Livewire\Administration\AccessGroup;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Group Akses')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public bool $showForm = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $nama_role = '';

    public string $formStep = 'detail';

    public string $selectedUserId = '';

    /** @var array<int, int> */
    public array $permissionIds = [];

    /** @var array<int, int> */
    public array $userIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $role = Role::query()
            ->with(['permissions:id', 'users:id'])
            ->findOrFail($id);

        $this->showForm = true;
        $this->formStep = 'detail';
        $this->editingId = $role->id;
        $this->nama_role = $role->nama_role;
        $this->permissionIds = $role->permissions->pluck('id')->all();
        $this->userIds = $role->users->pluck('id')->all();
    }

    public function manageAccess(): void
    {
        $this->validate([
            'nama_role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'nama_role')->ignore($this->editingId),
            ],
            'userIds' => ['array'],
            'userIds.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $this->formStep = 'access';
    }

    public function backToDetails(): void
    {
        $this->formStep = 'detail';
    }

    public function addUserFromPicker(string|int $userId): void
    {
        $this->addUser($userId);

        $this->selectedUserId = '';
    }

    private function addUser(string|int|null $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $userId = (int) $userId;

        User::findOrFail($userId);

        if (! in_array($userId, $this->userIds, true)) {
            $this->userIds[] = $userId;
        }
    }

    public function removeUser(int $userId): void
    {
        $this->userIds = array_values(array_filter(
            $this->userIds,
            fn (int $selectedUserId): bool => $selectedUserId !== $userId,
        ));
    }

    public function save(): void
    {
        $validated = $this->validate([
            'nama_role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'nama_role')->ignore($this->editingId),
            ],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', Rule::exists('permissions', 'id')],
            'userIds' => ['array'],
            'userIds.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $role = Role::query()->updateOrCreate(
            ['id' => $this->editingId],
            ['nama_role' => $validated['nama_role']],
        );

        $role->permissions()->sync($this->permissionIds);
        $role->users()->sync($this->userIds);

        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        Role::findOrFail($id);

        $this->resetErrorBag('delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $role = Role::findOrFail($this->deletingId);

        try {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Group akses masih digunakan pada data approval.');

            return;
        }

        $this->cancelDelete();
        $this->resetPage();
    }

    public function cancelDelete(): void
    {
        $this->resetErrorBag('delete');
        $this->reset(['deletingId', 'showDeleteModal']);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function getRolesProperty(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search !== '', function ($query): void {
                $query->where('nama_role', 'like', '%'.$this->search.'%');
            })
            ->orderBy('nama_role')
            ->paginate($this->perPage);
    }

    public function getPermissionsByModuleProperty(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }

    public function getUsersProperty(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'jabatan']);
    }

    public function getSelectedUsersProperty(): Collection
    {
        return $this->users
            ->whereIn('id', $this->userIds)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.administration.access-groups.index', [
            'roles' => $this->roles,
            'permissionsByModule' => $this->permissionsByModule,
            'users' => $this->users,
            'selectedUsers' => $this->selectedUsers,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'showForm',
            'nama_role',
            'formStep',
            'selectedUserId',
            'permissionIds',
            'userIds',
        ]);

        $this->formStep = 'detail';
    }
}
