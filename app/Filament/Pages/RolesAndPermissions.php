<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Permission as PermissionModel;
use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\UserRole;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class RolesAndPermissions extends Page
{
    protected static ?string $title = 'Roles y permisos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración y Sistema';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.roles-and-permissions';

    /** @var list<string> */
    public array $adminPermissions = [];

    #[Locked]
    public bool $canManageAdminRole = false;

    /** @var list<string> */
    #[Locked]
    public array $savedAdminPermissions = [];

    #[Locked]
    public string $permissionsRevision = '';

    public string $permissionSearch = '';

    public static function canAccess(): bool
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;

        return $user && Gate::forUser($user)->allows('viewAny', User::class);
    }

    public function mount(): void
    {
        $this->authorizeViewer();
        $this->loadPermissions();
    }

    public function saveAdminPermissions(): void
    {
        $actor = $this->authorizeManager();
        $this->validateSelection();
        if (! RolePermission::supportsPermissionState()) {
            throw ValidationException::withMessages(['adminPermissions' => 'La actualización del módulo está pendiente. Contacta al responsable de la instalación para habilitar los cambios.']);
        }
        foreach ($this->adminPermissions as $name) {
            $prerequisite = $this->prerequisite(Permission::from($name));
            if ($prerequisite && ! in_array($prerequisite->value, $this->adminPermissions, true)) {
                throw ValidationException::withMessages(['adminPermissions' => 'Para conceder «'.Permission::from($name)->getLabel().'», activa también «'.$prerequisite->getLabel().'».']);
            }
        }

        DB::transaction(function () use ($actor): void {
            $currentActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($currentActor->is_active && $currentActor->isSuperAdmin(), 403);
            if (! hash_equals($this->permissionsRevision, RolePermission::administratorRevision())) {
                throw ValidationException::withMessages(['adminPermissions' => 'Los permisos cambiaron en otra sesión. Recarga los permisos guardados y revisa tu selección antes de guardar.']);
            }
            $rows = [];
            $models = PermissionModel::query()->whereIn('name', array_map(fn (Permission $permission): string => $permission->value, Permission::cases()))->get()->keyBy('name');
            foreach (Permission::cases() as $permission) {
                $model = $models->get($permission->value) ?? PermissionModel::query()->firstOrCreate(['name' => $permission->value], [
                    'label' => $permission->getLabel(), 'module' => $permission->module(), 'action' => $permission->action(),
                ]);
                foreach ([UserRole::Admin, UserRole::Administrator] as $role) {
                    $rows[] = ['role' => $role->value, 'permission_id' => $model->id, 'is_granted' => in_array($permission->value, $this->adminPermissions, true)];
                }
            }
            DB::table('role_permissions')->upsert($rows, ['role', 'permission_id'], ['is_granted']);
        });

        $this->loadPermissions();
        Notification::make()->title('Permisos del administrador guardados')->body('La plantilla se aplica a las cuentas de administrador. Los permisos individuales adicionales se conservan.')->success()->send();
    }

    public function togglePermission(string $name, bool $enable): void
    {
        $this->authorizeManager();
        $this->validateSelection();
        $permission = Permission::tryFrom($name);
        if (! $permission) {
            throw ValidationException::withMessages(['adminPermissions' => 'El permiso indicado no existe.']);
        }
        $this->changePermission($permission, $enable);
    }

    public function toggleModule(string $module, bool $enable): void
    {
        $this->authorizeManager();
        $this->validateSelection();
        $permissions = array_filter(Permission::cases(), fn (Permission $permission): bool => $permission->module() === $module);
        if ($permissions === []) {
            throw ValidationException::withMessages(['adminPermissions' => 'El módulo indicado no existe.']);
        }
        foreach ($permissions as $permission) {
            $this->changePermission($permission, $enable);
        }
    }

    public function selectAll(): void
    {
        $this->authorizeManager();
        $this->adminPermissions = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());
        $this->resetValidation();
    }

    public function deselectAll(): void
    {
        $this->authorizeManager();
        $this->adminPermissions = [];
        $this->resetValidation();
    }

    public function resetAdminPermissions(): void
    {
        $this->authorizeViewer();
        $this->loadPermissions();
        $this->resetValidation();
    }

    public function updatedPermissionSearch(): void
    {
        $this->validateOnly('permissionSearch', ['permissionSearch' => ['string', 'max:120']]);
    }

    private function changePermission(Permission $permission, bool $enable): void
    {
        if ($enable) {
            $this->adminPermissions[] = $permission->value;
            if ($prerequisite = $this->prerequisite($permission)) {
                $this->adminPermissions[] = $prerequisite->value;
            }
            $this->adminPermissions = array_values(array_unique($this->adminPermissions));
        } else {
            $this->adminPermissions = array_values(array_filter($this->adminPermissions,
                fn (string $name): bool => $name !== $permission->value && $this->prerequisite(Permission::from($name)) !== $permission));
        }
        $this->resetValidation();
    }

    private function prerequisite(Permission $permission): ?Permission
    {
        if ($permission === Permission::TestMail) {
            return Permission::ViewSettings;
        }

        return $permission->action() === 'view' ? null : Permission::tryFrom($permission->module().'.view');
    }

    private function validateSelection(): void
    {
        $this->validate([
            'adminPermissions' => ['present', 'array', 'max:'.count(Permission::cases())],
            'adminPermissions.*' => ['required', 'string', 'distinct', Rule::in(array_map(fn (Permission $permission): string => $permission->value, Permission::cases()))],
        ], attributes: ['adminPermissions' => 'permisos del administrador', 'adminPermissions.*' => 'permiso']);
    }

    private function authorizeViewer(): User
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user && Gate::forUser($user)->allows('viewAny', User::class), 403);

        return $user;
    }

    private function authorizeManager(): User
    {
        $user = $this->authorizeViewer();
        abort_unless($user->is_active && $user->isSuperAdmin(), 403);

        return $user;
    }

    private function loadPermissions(): void
    {
        RolePermission::clearCache();
        $this->savedAdminPermissions = array_values(array_filter(RolePermission::permissionsFor(UserRole::Admin), fn (string $name): bool => Permission::tryFrom($name) !== null));
        $this->adminPermissions = $this->savedAdminPermissions;
        $this->permissionsRevision = RolePermission::administratorRevision();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('assignRoles')->label('Gestionar usuarios')->icon(Heroicon::OutlinedUsers)->url(UserResource::getUrl())];
    }

    protected function getViewData(): array
    {
        $actor = $this->authorizeViewer();
        $this->canManageAdminRole = $actor->isSuperAdmin();
        $roles = [UserRole::SuperAdmin, UserRole::Admin, UserRole::Editor, UserRole::User];
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->value] = RolePermission::permissionsFor($role);
        }
        $userCounts = User::query()->select('role')->selectRaw('COUNT(*) as total')->groupBy('role')->pluck('total', 'role')->all();
        $userCounts[UserRole::Admin->value] = (int) ($userCounts[UserRole::Admin->value] ?? 0) + (int) ($userCounts[UserRole::Administrator->value] ?? 0);
        $modules = [
            'tools' => ['title' => 'Herramientas', 'description' => 'Fichas de herramientas, alternativas y publicación.'],
            'tool_categories' => ['title' => 'Categorías de herramientas', 'description' => 'Clasificación del directorio de herramientas.'],
            'workflows' => ['title' => 'Flujos de trabajo', 'description' => 'Casos paso a paso con herramientas y plantillas.'],
            'templates' => ['title' => 'Plantillas', 'description' => 'Prompts, instrucciones y automatizaciones para copiar o descargar.'],
            'posts' => ['title' => 'Artículos y blog', 'description' => 'Publicación y gestión de artículos.'],
            'categories' => ['title' => 'Categorías del blog', 'description' => 'Organización de artículos por categoría.'],
            'projects' => ['title' => 'Proyectos y portafolio', 'description' => 'Publicación, edición y orden de proyectos.'],
            'project_categories' => ['title' => 'Categorías de proyectos', 'description' => 'Clasificación del portafolio.'],
            'services' => ['title' => 'Servicios', 'description' => 'Catálogo de servicios del sitio.'],
            'testimonials' => ['title' => 'Testimonios', 'description' => 'Opiniones y reseñas de clientes.'],
            'faqs' => ['title' => 'Preguntas frecuentes', 'description' => 'Preguntas y respuestas del sitio.'],
            'media' => ['title' => 'Biblioteca multimedia', 'description' => 'Carga, información y eliminación de imágenes.'],
            'legal_pages' => ['title' => 'Páginas legales', 'description' => 'Términos, privacidad y políticas.'],
            'contact_messages' => ['title' => 'Mensajes de contacto', 'description' => 'Consultas recibidas y seguimiento de su estado.'],
            'contact_assistant' => ['title' => 'Asistente de contacto', 'description' => 'Conversación guiada y destino de WhatsApp.'],
            'settings' => ['title' => 'Configuración del sitio', 'description' => 'Identidad, SEO, cookies, configuración de correo y caché.'],
            'mail' => ['title' => 'Correo de prueba', 'description' => 'Enviar pruebas desde la configuración de correo.'],
            'redirects' => ['title' => 'Redirecciones', 'description' => 'Gestión de destinos y enlaces del sitio.'],
            'users' => ['title' => 'Usuarios', 'description' => 'Cuentas, roles y permisos individuales adicionales.'],
        ];
        $groupedPermissions = collect(Permission::cases())->groupBy(fn (Permission $permission): string => $permission->module())->all();
        $needle = mb_strtolower(mb_substr(trim($this->permissionSearch), 0, 120));
        $filteredModules = array_filter($modules, function (array $metadata, string $key) use ($needle, $groupedPermissions): bool {
            return $needle === '' || str_contains(mb_strtolower($metadata['title'].' '.$metadata['description']), $needle)
                || collect($groupedPermissions[$key] ?? [])->contains(fn (Permission $permission): bool => str_contains(mb_strtolower($permission->getLabel().' '.$permission->value), $needle));
        }, ARRAY_FILTER_USE_BOTH);
        $selection = array_values(array_filter($this->adminPermissions, fn (mixed $name): bool => is_string($name) && Permission::tryFrom($name) !== null));
        $saved = $this->savedAdminPermissions;
        sort($selection);
        sort($saved);

        return [
            'roles' => $roles, 'rolePermissions' => $rolePermissions, 'userCounts' => $userCounts,
            'modules' => $filteredModules, 'groupedPermissions' => $groupedPermissions,
            'totalPermissions' => count(Permission::cases()), 'selectedCount' => count(array_unique($selection)),
            'hasChanges' => $selection !== $saved,
            'storageReady' => RolePermission::supportsPermissionState(),
            'canManageAdminRole' => $this->canManageAdminRole,
        ];
    }
}
