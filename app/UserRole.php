<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Administrator = 'administrator';
    case Editor = 'editor';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrador',
            self::Admin, self::Administrator => 'Administrador',
            self::Editor => 'Editor',
            self::User => 'Usuario',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin || $this === self::Administrator || $this === self::SuperAdmin;
    }

    public function isEditor(): bool
    {
        return $this === self::Editor;
    }

    public function isUser(): bool
    {
        return $this === self::User;
    }

    public function canAccessAdminPanel(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Admin, self::Administrator, self::Editor => true,
            self::User => false,
        };
    }

    public static function fromValue(string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom($value) ?? self::User;
    }

    public function allows(Permission|string $permission): bool
    {
        $perm = is_string($permission) ? Permission::tryFrom($permission) : $permission;
        if (! $perm) {
            return false;
        }

        return match ($this) {
            self::SuperAdmin, self::Admin, self::Administrator => true,
            self::Editor => in_array($perm, self::editorPermissions(), true),
            self::User => false,
        };
    }

    /**
     * @return array<Permission>
     */
    public static function editorPermissions(): array
    {
        return [
            Permission::ViewMediaAsset,
            Permission::CreateMediaAsset,
            Permission::UpdateMediaAsset,
            Permission::ViewPostCategory,
            Permission::CreatePostCategory,
            Permission::UpdatePostCategory,
            Permission::ViewPost,
            Permission::CreatePost,
            Permission::UpdatePost,
            Permission::ViewService,
            Permission::CreateService,
            Permission::UpdateService,
            Permission::ViewTestimonial,
            Permission::CreateTestimonial,
            Permission::UpdateTestimonial,
            Permission::ViewFaq,
            Permission::CreateFaq,
            Permission::UpdateFaq,
            Permission::ViewProjectCategory,
            Permission::CreateProjectCategory,
            Permission::UpdateProjectCategory,
            Permission::DeleteProjectCategory,
            Permission::ViewProject,
            Permission::CreateProject,
            Permission::UpdateProject,
            Permission::DeleteProject,
        ];
    }
}
