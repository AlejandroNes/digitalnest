<?php

namespace App\Models;

use App\Permission as PermissionEnum;
use App\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermission extends Model
{
    public $timestamps = false;

    protected $table = 'role_permissions';

    protected $fillable = ['role', 'permission_id', 'is_granted'];

    /**
     * @var array<string, array<string>>|null
     */
    protected static ?array $memoizedPermissions = null;

    protected static ?Request $memoizedRequest = null;

    protected static bool $permissionStorageFailed = false;

    public static function clearCache(): void
    {
        static::$memoizedPermissions = null;
        static::$memoizedRequest = null;
        static::$permissionStorageFailed = false;
    }

    public static function allows(UserRole $role, PermissionEnum $permission): bool
    {
        return in_array($permission->value, static::permissionsFor($role), true);
    }

    /** @return list<string> */
    public static function permissionsFor(UserRole $role): array
    {
        if ($role === UserRole::SuperAdmin) {
            return array_map(fn (PermissionEnum $permission): string => $permission->value, PermissionEnum::cases());
        }
        if ($role === UserRole::User) {
            return [];
        }
        if (static::$memoizedRequest !== request()) {
            static::clearCache();
            static::$memoizedRequest = request();
        }

        try {
            if (static::$memoizedPermissions === null) {
                if (! Schema::hasTable('role_permissions')) {
                    return static::defaultPermissions($role);
                }

                $query = DB::table('role_permissions')
                    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                    ->select('role_permissions.role', 'permissions.name');
                if (static::supportsPermissionState()) {
                    $query->addSelect('role_permissions.is_granted');
                }
                static::$memoizedPermissions = $query
                    ->get()
                    ->groupBy('role')
                    ->map(fn ($rows): array => $rows->filter(fn ($row): bool => (bool) ($row->is_granted ?? true) && PermissionEnum::tryFrom($row->name) !== null)->pluck('name')->all())
                    ->all();
            }
            if (static::$permissionStorageFailed) {
                return [];
            }
            $roleKey = $role->isAdmin() ? UserRole::Admin->value : $role->value;
            if (isset(static::$memoizedPermissions[$roleKey])) {
                return static::$memoizedPermissions[$roleKey];
            }
            if ($role->isAdmin() && isset(static::$memoizedPermissions[UserRole::Administrator->value])) {
                return static::$memoizedPermissions[UserRole::Administrator->value];
            }
        } catch (\Throwable $exception) {
            static::$memoizedPermissions = [];
            static::$permissionStorageFailed = true;
            report($exception);

            return [];
        }

        return static::defaultPermissions($role);
    }

    public static function supportsPermissionState(): bool
    {
        return Schema::hasColumn('role_permissions', 'is_granted');
    }

    public static function administratorRevision(): string
    {
        $query = DB::table('role_permissions')->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role', [UserRole::Admin->value, UserRole::Administrator->value])
            ->select('role_permissions.role', 'permissions.name')->orderBy('role_permissions.role')->orderBy('permissions.name');
        $supportsState = static::supportsPermissionState();
        if ($supportsState) {
            $query->addSelect('role_permissions.is_granted');
        }

        return hash('sha256', json_encode([$supportsState, $query->get()->toArray()], JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    private static function defaultPermissions(UserRole $role): array
    {
        return array_values(array_map(fn (PermissionEnum $permission): string => $permission->value,
            array_filter(PermissionEnum::cases(), fn (PermissionEnum $permission): bool => $role->allows($permission))));
    }
}
