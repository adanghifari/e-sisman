<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(config('access.permissions', []));
        $permissionCodes = $permissions->pluck('code')->all();

        DB::table('role_permissions')
            ->whereIn(
                'permission_id',
                DB::table('permissions')
                    ->whereNotIn('code', $permissionCodes)
                    ->select('id'),
            )
            ->delete();

        DB::table('permissions')
            ->whereNotIn('code', $permissionCodes)
            ->delete();

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                    'route' => $permission['route'] ?? null,
                    'action' => $permission['action'] ?? 'view',
                ],
            );
        }
    }
}
