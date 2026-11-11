<?php

namespace Database\Seeders;

use App\Models\Permission as PermissionModel;
use App\Models\RolePermission;
use App\Permission;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissionModels = [];
        foreach (Permission::cases() as $permission) {
            $permissionModels[$permission->value] = PermissionModel::query()->updateOrCreate(
                ['name' => $permission->value],
                [
                    'label' => $permission->getLabel(),
                    'module' => $permission->module(),
                    'action' => $permission->action(),
                ]
            );
        }

        $roleAssignments = [
            UserRole::SuperAdmin->value => Permission::cases(),
            UserRole::Admin->value => Permission::cases(),
            UserRole::Editor->value => UserRole::editorPermissions(),
            UserRole::User->value => [],
        ];

        foreach ($roleAssignments as $role => $permissions) {
            if ($role === UserRole::Admin->value && DB::table('role_permissions')
                ->whereIn('role', [UserRole::Admin->value, UserRole::Administrator->value])->exists()) {
                continue;
            }
            foreach ($permissions as $permission) {
                $permissionModel = $permissionModels[$permission->value] ?? null;
                if ($permissionModel) {
                    DB::table('role_permissions')->updateOrInsert(
                        [
                            'role' => $role,
                            'permission_id' => $permissionModel->id,
                        ],
                        []
                    );
                }
            }
        }

        RolePermission::clearCache();
    }
}
