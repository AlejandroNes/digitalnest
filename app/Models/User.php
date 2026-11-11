<?php

namespace App\Models;

use App\Models\Permission as PermissionModel;
use App\Permission as PermissionEnum;
use App\UserRole;
use Database\Factories\UserFactory;
use DomainException;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const SUPER_ADMIN_EMAIL = 'alejandrones.tech@gmail.com';

    protected $attributes = ['role' => 'editor', 'is_active' => true];

    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->role === UserRole::SuperAdmin) {
                if (! $user->isDesignatedSuperAdmin()) {
                    throw new DomainException('Solo la cuenta designada puede recibir el rol de superadministrador.');
                }

                if (static::query()->where('role', UserRole::SuperAdmin)->exists()) {
                    throw new DomainException('Ya existe una cuenta superadministradora en la instalación.');
                }
            } elseif ($user->isDesignatedSuperAdmin()) {
                $user->role = UserRole::SuperAdmin;
            }
        });

        static::updating(function (self $user): void {
            $originalRole = $user->getOriginal('role');
            $originalEmail = $user->getOriginal('email');

            $wasSuperAdmin = ($originalRole === UserRole::SuperAdmin->value || $originalRole === UserRole::SuperAdmin)
                || strcasecmp((string) $originalEmail, self::SUPER_ADMIN_EMAIL) === 0;

            if ($wasSuperAdmin) {
                if ($user->isDirty('role') && $user->role !== UserRole::SuperAdmin) {
                    throw new DomainException('La cuenta superadministradora no puede ser degradada ni cambiar de rol.');
                }

                if ($user->isDirty('email') && ! $user->isDesignatedSuperAdmin()) {
                    throw new DomainException('El correo del superadministrador está protegido y no puede ser modificado.');
                }

                if ($user->isDirty('is_active') && ! $user->is_active) {
                    throw new DomainException('La cuenta superadministradora no puede ser desactivada.');
                }
            } else {
                if ($user->isDirty('role') && $user->role === UserRole::SuperAdmin) {
                    if (! $user->isDesignatedSuperAdmin()) {
                        throw new DomainException('Ningún otro usuario puede recibir el rol de superadministrador.');
                    }
                }
            }
        });

        static::deleting(function (self $user): void {
            if ($user->role === UserRole::SuperAdmin || $user->isDesignatedSuperAdmin()) {
                throw new DomainException('La cuenta superadministradora está protegida y no puede ser eliminada.');
            }

            if ($user->role === UserRole::SuperAdmin && static::query()->where('role', UserRole::SuperAdmin)->count() <= 1) {
                throw new DomainException('No se puede eliminar el último superadministrador.');
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionModel::class, 'user_permissions');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return $this->role?->canAccessAdminPanel() ?? false;
        }

        if ($panel->getId() === 'panel') {
            return $this->role === UserRole::User
                && (bool) config('features.client_panel_enabled', false);
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isDesignatedSuperAdmin(): bool
    {
        return strcasecmp((string) $this->email, self::SUPER_ADMIN_EMAIL) === 0;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin || $this->role === UserRole::Administrator || $this->role === UserRole::SuperAdmin;
    }

    public function isEditor(): bool
    {
        return $this->role === UserRole::Editor;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }

    /** @var array<string>|null */
    protected ?array $memoizedUserPermissions = null;

    public function hasPermission(PermissionEnum|string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $permissionEnum = is_string($permission)
            ? PermissionEnum::tryFrom($permission)
            : $permission;
        if (! $permissionEnum) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->role === UserRole::User || $this->role === null) {
            return false;
        }

        if (RolePermission::allows($this->role, $permissionEnum)) {
            return true;
        }

        if ($this->memoizedUserPermissions === null) {
            if ($this->relationLoaded('permissions')) {
                $this->memoizedUserPermissions = $this->permissions->pluck('name')->all();
            } else {
                try {
                    $this->memoizedUserPermissions = $this->permissions()->pluck('name')->all();
                } catch (\Throwable) {
                    $this->memoizedUserPermissions = [];
                }
            }
        }

        return in_array($permissionEnum->value, $this->memoizedUserPermissions, true);
    }

    public function givePermission(PermissionEnum|string $permission, ?self $actor = null): void
    {
        $this->memoizedUserPermissions = null;
        $permissionEnum = is_string($permission) ? PermissionEnum::from($permission) : $permission;

        if ($actor !== null) {
            $actor = $actor->fresh() ?? throw new AuthorizationException;
        }
        if ($actor !== null && ! $actor->isSuperAdmin()) {
            if (! $actor->hasPermission($permissionEnum)) {
                throw new AuthorizationException('No puedes otorgar un permiso que no posees.');
            }
            if ($actor->is($this)) {
                throw new AuthorizationException('No puedes elevar tus propios privilegios.');
            }
        }
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('grantPermission', [$this, $permissionEnum]);
        }

        $model = PermissionModel::query()->firstOrCreate(
            ['name' => $permissionEnum->value],
            [
                'label' => $permissionEnum->getLabel(),
                'module' => $permissionEnum->module(),
                'action' => $permissionEnum->action(),
            ]
        );

        $this->permissions()->syncWithoutDetaching([$model->id]);
        $this->unsetRelation('permissions');
    }

    public function revokePermission(PermissionEnum|string $permission, ?self $actor = null): void
    {
        $this->memoizedUserPermissions = null;
        $permissionEnum = is_string($permission) ? PermissionEnum::from($permission) : $permission;

        if ($actor !== null) {
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            if ($actor->is($this)) {
                throw new AuthorizationException('No puedes modificar tus propios permisos.');
            }
            Gate::forUser($actor)->authorize('update', $this);
        }

        $model = PermissionModel::query()->where('name', $permissionEnum->value)->first();
        if ($model) {
            $this->permissions()->detach($model->id);
        }
        $this->unsetRelation('permissions');
    }

    /**
     * @param  array<PermissionEnum|string>  $permissions
     */
    public function syncPermissions(array $permissions, ?self $actor = null): void
    {
        $this->memoizedUserPermissions = null;
        $permissionEnums = array_map(fn (PermissionEnum|string $permission): PermissionEnum => is_string($permission) ? PermissionEnum::from($permission) : $permission, $permissions);
        if ($actor !== null) {
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            if ($actor->is($this)) {
                throw new AuthorizationException('No puedes modificar tus propios permisos.');
            }
            Gate::forUser($actor)->authorize('update', $this);
            $existing = $this->permissions()->pluck('name')->all();
            foreach ($permissionEnums as $permission) {
                if (! in_array($permission->value, $existing, true)) {
                    Gate::forUser($actor)->authorize('grantPermission', [$this, $permission]);
                }
            }
        }
        DB::transaction(function () use ($permissionEnums): void {
            $permissionIds = [];
            foreach ($permissionEnums as $permission) {
                $model = PermissionModel::query()->firstOrCreate(['name' => $permission->value], [
                    'label' => $permission->getLabel(), 'module' => $permission->module(), 'action' => $permission->action(),
                ]);
                $permissionIds[] = $model->id;
            }
            $this->permissions()->sync($permissionIds);
        });
        $this->unsetRelation('permissions');
    }

    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?UserRole => match (true) {
                $value instanceof UserRole => $value,
                is_string($value) => UserRole::tryFrom($value),
                default => null,
            },
            set: fn (mixed $value): ?string => match (true) {
                $value instanceof UserRole => $value->value,
                is_string($value) => $value,
                default => null,
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
