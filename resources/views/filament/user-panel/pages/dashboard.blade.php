<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Resumen de tu cuenta
            </x-slot>
            <x-slot name="description">
                Información de seguridad y estado de tu cuenta de cliente.
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Nombre completo</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ auth()->user()?->name }}</span>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Correo electrónico</span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ auth()->user()?->email }}</span>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Estado del acceso</span>
                    <div class="flex items-center gap-2">
                        <x-filament::badge color="success">Cuenta Activa</x-filament::badge>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Seguridad y Credenciales
            </x-slot>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Puedes actualizar tu contraseña en cualquier momento a través de la sección de perfil personal, verificando tu contraseña actual.
                </p>
                <x-filament::button :href="filament()->getProfileUrl()" tag="a" color="gray" icon="heroicon-o-key">
                    Ir a mi perfil
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
