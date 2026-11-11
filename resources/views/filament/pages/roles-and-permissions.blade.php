<x-filament-panels::page>
    <div class="roles-permissions">
        <div class="roles-overview">
            @foreach($roles as $role)
                <div class="roles-summary" wire:key="role-summary-{{ $role->value }}">
                    <div class="roles-summary-heading">
                        <h2>{{ $role->getLabel() }}</h2>
                        <x-filament::badge :color="$role->value === 'admin' ? 'primary' : 'gray'">
                            {{ match($role->value) { 'super_admin' => 'Protegido', 'admin' => 'Configurable', 'editor' => 'Contenido', default => 'Sin acceso admin' } }}
                        </x-filament::badge>
                    </div>
                    <p>{{ match($role->value) {
                        'super_admin' => 'Cuenta protegida con acceso administrativo completo.',
                        'admin' => 'Acceso definido por la plantilla de permisos guardada.',
                        'editor' => 'Permisos de contenido definidos por el sistema.',
                        default => 'Cuenta de usuario sin acceso al panel administrativo.',
                    } }}</p>
                    <div class="roles-summary-counts">
                        <span>{{ $userCounts[$role->value] ?? 0 }} {{ (int) ($userCounts[$role->value] ?? 0) === 1 ? 'cuenta' : 'cuentas' }}</span>
                        <span>{{ count($rolePermissions[$role->value]) }} {{ count($rolePermissions[$role->value]) === 1 ? 'permiso del rol' : 'permisos del rol' }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <x-filament::section heading="Permisos del administrador" :description="$canManageAdminRole ? 'Elige los accesos del rol. Los cambios se aplican a las cuentas de administrador al guardar.' : 'Consulta los accesos que concede el rol Administrador. Los permisos individuales adicionales se gestionan en Usuarios.'">
            @if(! $canManageAdminRole)
                <p class="roles-notice" role="status">Puedes consultar los permisos. Solo el superadministrador puede modificar esta plantilla.
                    @if((int) ($userCounts['super_admin'] ?? 0) === 0) No hay una cuenta superadministradora configurada. Solicita al responsable de la instalación que la prepare. @endif
                </p>
            @elseif(! $storageReady)
                <p class="roles-notice" role="status">La actualización del módulo está pendiente. Contacta al responsable de la instalación para habilitar los cambios.</p>
            @endif

            <div class="roles-toolbar">
                <div class="roles-search">
                    <label for="role-permission-search" class="sr-only">Buscar módulos o permisos</label>
                    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                        <x-filament::input id="role-permission-search" type="search" wire:model.live.debounce.300ms="permissionSearch" maxlength="120" placeholder="Buscar módulos o permisos…" />
                    </x-filament::input.wrapper>
                    @error('permissionSearch')<p class="roles-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="roles-selection-count" aria-live="polite">
                    <strong>{{ $selectedCount }} de {{ $totalPermissions }}</strong>
                    <span>permisos seleccionados</span>
                    <x-filament::badge :color="$hasChanges ? 'warning' : 'gray'">{{ $hasChanges ? 'Sin guardar' : 'Guardado' }}</x-filament::badge>
                </div>
            </div>

            <div class="roles-selection-actions">
                @if($canManageAdminRole)
                    <x-filament::button type="button" color="gray" wire:click="selectAll" wire:loading.attr="disabled">Seleccionar todos</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="deselectAll" wire:loading.attr="disabled">Quitar todos</x-filament::button>
                @endif
                <x-filament::button type="button" color="gray" wire:click="resetAdminPermissions" wire:loading.attr="disabled">Recargar guardados</x-filament::button>
            </div>
            @if($canManageAdminRole)
                <p class="roles-help">Activar una acción añade el permiso de lectura necesario. Quitar la lectura desactiva sus acciones. Los permisos individuales adicionales se gestionan en Usuarios.</p>
            @endif
            @error('adminPermissions')<p class="roles-error" role="alert">{{ $message }}</p>@enderror
            @foreach($errors->get('adminPermissions.*') as $messages)
                @foreach($messages as $message)<p class="roles-error" role="alert">{{ $message }}</p>@endforeach
            @endforeach

            <div class="roles-module-grid">
                @forelse($modules as $moduleKey => $metadata)
                    @php($modulePermissions = $groupedPermissions[$moduleKey] ?? collect())
                    @php($moduleSelected = $modulePermissions->filter(fn ($permission) => in_array($permission->value, $adminPermissions, true))->count())
                    <section class="roles-module" aria-labelledby="role-module-{{ $moduleKey }}" wire:key="role-module-{{ $moduleKey }}">
                        <div class="roles-module-heading">
                            <div>
                                <h3 id="role-module-{{ $moduleKey }}">{{ $metadata['title'] }}</h3>
                                <p>{{ $metadata['description'] }}</p>
                            </div>
                            <span class="roles-module-count">{{ $moduleSelected }}/{{ count($modulePermissions) }}</span>
                        </div>
                        @if($canManageAdminRole)
                            <div class="roles-module-actions">
                                <button type="button" wire:click="toggleModule('{{ $moduleKey }}', true)" wire:loading.attr="disabled" class="roles-module-action" aria-label="Seleccionar todos los permisos de {{ $metadata['title'] }}">Todos</button>
                                <button type="button" wire:click="toggleModule('{{ $moduleKey }}', false)" wire:loading.attr="disabled" class="roles-module-action" aria-label="Quitar todos los permisos de {{ $metadata['title'] }}">Ninguno</button>
                            </div>
                        @endif
                        <div class="roles-permission-options">
                            @foreach($modulePermissions as $permission)
                                @if($canManageAdminRole)
                                    <label class="roles-permission-option" for="role-permission-{{ str_replace('.', '-', $permission->value) }}">
                                        <input id="role-permission-{{ str_replace('.', '-', $permission->value) }}" type="checkbox" value="{{ $permission->value }}" wire:change="togglePermission('{{ $permission->value }}', $event.target.checked)" wire:loading.attr="disabled" @checked(in_array($permission->value, $adminPermissions, true))>
                                        <span>{{ $permission->getLabel() }}</span>
                                    </label>
                                @else
                                    @php($allowed = in_array($permission->value, $adminPermissions, true))
                                    <div class="roles-permission-option roles-permission-readonly">
                                        <span @class(['roles-permission-mark', 'roles-permission-mark-allowed' => $allowed]) role="img" aria-label="{{ $allowed ? 'Permitido' : 'Denegado' }}"><x-filament::icon :icon="$allowed ? 'heroicon-m-check' : 'heroicon-m-minus'" class="size-4" /></span>
                                        <span>{{ $permission->getLabel() }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="roles-empty" role="status">
                        <p>No hay módulos o permisos que coincidan con tu búsqueda.</p>
                        <x-filament::button type="button" color="gray" wire:click="$set('permissionSearch', '')">Limpiar búsqueda</x-filament::button>
                    </div>
                @endforelse
            </div>
            @if($canManageAdminRole)
                <div class="roles-savebar">
                    <div aria-live="polite">
                        <strong>{{ $hasChanges ? 'Tienes cambios sin guardar' : 'La plantilla está guardada' }}</strong>
                        <p>{{ $selectedCount === 0 ? 'Sin permisos del rol. Los permisos individuales adicionales seguirán activos.' : 'La cuenta superadministradora conserva su acceso completo.' }}</p>
                    </div>
                    <x-filament::button type="button" wire:click="saveAdminPermissions" wire:loading.attr="disabled" :disabled="! $hasChanges || ! $storageReady" icon="heroicon-o-check">Guardar permisos</x-filament::button>
                </div>
            @endif
        </x-filament::section>

        <details class="roles-comparison">
            <summary>Comparar permisos guardados por rol</summary>
            <div class="roles-comparison-body">
                <p class="roles-help">Esta comparación muestra los permisos guardados del rol para cuentas activas. Los cambios sin guardar y los permisos individuales adicionales no se incluyen.</p>
                <div class="roles-comparison-desktop">
                    <table class="roles-comparison-table">
                        <caption class="sr-only">Permisos guardados por rol</caption>
                        <thead><tr><th scope="col">Permiso</th>@foreach($roles as $role)<th scope="col">{{ $role->getLabel() }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($modules as $moduleKey => $metadata)
                                <tr class="roles-comparison-module"><th scope="rowgroup" colspan="5">{{ $metadata['title'] }}</th></tr>
                                @foreach($groupedPermissions[$moduleKey] ?? [] as $permission)
                                    <tr>
                                        <th scope="row">{{ $permission->getLabel() }}</th>
                                        @foreach($roles as $role)
                                            @php($allowed = in_array($permission->value, $rolePermissions[$role->value], true))
                                            <td><span @class(['roles-access', 'roles-access-allowed' => $allowed])>{{ $allowed ? 'Permitido' : 'Denegado' }}</span></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="roles-comparison-mobile">
                    @foreach($modules as $moduleKey => $metadata)
                        <h3 class="roles-comparison-mobile-title">{{ $metadata['title'] }}</h3>
                        <dl>
                            @foreach($groupedPermissions[$moduleKey] ?? [] as $permission)
                                <div class="roles-comparison-item">
                                    <dt>{{ $permission->getLabel() }}</dt>
                                    @foreach($roles as $role)
                                        @php($allowed = in_array($permission->value, $rolePermissions[$role->value], true))
                                        <dd><span>{{ $role->getLabel() }}</span><span @class(['roles-access', 'roles-access-allowed' => $allowed])>{{ $allowed ? 'Permitido' : 'Denegado' }}</span></dd>
                                    @endforeach
                                </div>
                            @endforeach
                        </dl>
                    @endforeach
                </div>
            </div>
        </details>
    </div>
</x-filament-panels::page>
