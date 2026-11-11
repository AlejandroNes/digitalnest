<x-filament-panels::page>
    @php($summary = $this->savedSummary())
    <x-filament::section heading="Configuración guardada" description="Este resumen muestra los valores que se usarán, con los respaldos del sitio.">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium">Notificaciones</dt><dd>{{ $summary['enabled'] ? 'Activadas' : 'Desactivadas' }}</dd></div>
            <div><dt class="font-medium">Servidor</dt><dd>{{ $summary['mode'] }}</dd></div>
            <div><dt class="font-medium">Remitente efectivo</dt><dd class="break-all">{{ $summary['sender'] ?: 'Sin configurar' }}</dd></div>
            <div><dt class="font-medium">Destinatarios efectivos</dt><dd class="break-all">{{ implode(', ', $summary['recipients'] ?? []) ?: 'Sin configurar' }}</dd></div>
            <div><dt class="font-medium">Credenciales del panel</dt><dd>{{ $summary['credentials'] ? 'Guardadas; no se muestran' : 'Sin credenciales completas' }}</dd></div>
        </dl>
        <p class="mt-4 text-sm" role="status">{{ $summary['issue'] }}</p>
    </x-filament::section>
    <x-filament::section heading="Ayuda rápida" collapsible collapsed>
        <ol class="list-decimal space-y-3 pl-5 text-sm">
            <li>Activa notificaciones, revisa los destinatarios del equipo y usa un remitente autorizado por tu proveedor.</li>
            <li>Usa el servidor preparado para el sitio o los datos SMTP del proveedor. Si no aparece, pide al responsable que lo habilite. 465 utiliza TLS y 587 STARTTLS; otros puertos deben estar autorizados.</li>
            <li>Dejar usuario y contraseña vacíos conserva los valores guardados. Al cambiar de servidor, sustituye ambos o elimina explícitamente las credenciales. No uses la contraseña del panel como contraseña SMTP.</li>
            <li>Guarda primero y envía una prueba a un buzón que puedas revisar. Comprueba Recibidos y Spam: aceptación por el servidor no garantiza recepción.</li>
            <li>Si falla, revisa los datos y consulta al responsable con la hora y el resultado, sin compartir contraseñas. Se permiten hasta tres pruebas por minuto.</li>
        </ol>
    </x-filament::section>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button class="w-full sm:w-auto" type="submit" wire:loading.attr="disabled" wire:target="save">Guardar configuración</x-filament::button>
    </form>
    <x-filament::section heading="Enviar correo de prueba" description="La prueba utiliza la configuración guardada. Guarda los cambios antes de enviar. Comprueba Recibidos y Spam para confirmar la recepción.">
    <form wire:submit="sendTest" class="space-y-4">
        <label for="test-recipient" class="block text-sm font-medium">Destinatario del correo de prueba</label>
        <x-filament::input.wrapper>
            <x-filament::input id="test-recipient" type="email" wire:model="testRecipient" required maxlength="255" autocomplete="email" aria-describedby="test-recipient-error" aria-invalid="{{ $errors->has('testRecipient') ? 'true' : 'false' }}" />
        </x-filament::input.wrapper>
        <div id="test-recipient-error">
            @error('testRecipient')<p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
        </div>
        <x-filament::button class="w-full sm:w-auto" type="submit" wire:loading.attr="disabled" wire:target="sendTest">Enviar prueba con la configuración guardada</x-filament::button>
    </form>
    </x-filament::section>
</x-filament-panels::page>
