<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    private const DEFAULT_USER_ROLE = 'User';

    private const DEFAULT_USER_PERMISSION_CODES = [
        'dashboard.view',
        'documents.inbox.view',
        'documents.approval.view',
        'documents.approval.download',
        'documents.approval.preview',
        'documents.create.view',
        'documents.create.create',
        'documents.master.view',
        'document-templates.view',
        'document-templates.download',
    ];

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

        DB::table('roles')->updateOrInsert(
            ['nama_role' => 'SuperAdmin'],
            ['nama_role' => 'SuperAdmin'],
        );

        $superAdminRoleId = DB::table('roles')
            ->where('nama_role', 'SuperAdmin')
            ->value('id');

        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('roles')->updateOrInsert(
            ['nama_role' => self::DEFAULT_USER_ROLE],
            ['nama_role' => self::DEFAULT_USER_ROLE],
        );

        $defaultUserRoleId = DB::table('roles')
            ->where('nama_role', self::DEFAULT_USER_ROLE)
            ->value('id');

        $defaultUserPermissionIds = DB::table('permissions')
            ->whereIn('code', self::DEFAULT_USER_PERMISSION_CODES)
            ->pluck('id');

        foreach ($defaultUserPermissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $defaultUserRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        $nonDeveloperUserIds = DB::table('users')
            ->where(function ($query): void {
                $query
                    ->where('nik', '!=', '000000')
                    ->orWhereNull('nik');
            })
            ->where(function ($query): void {
                $query
                    ->where('email', '!=', 'developer@example.com')
                    ->orWhereNull('email');
            })
            ->pluck('id');

        foreach ($nonDeveloperUserIds as $userId) {
            DB::table('user_roles')->updateOrInsert([
                'role_id' => $defaultUserRoleId,
                'user_id' => $userId,
            ]);
        }
    }
}
