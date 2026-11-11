<x-filament-panels::page>
    <div class="assistant-settings">
        <div class="assistant-settings-status" aria-label="Configuración guardada">
            <div>
                <strong>Estado guardado</strong>
                <p>{{ $isPubliclyAvailable ? 'El chat está disponible para los visitantes.' : 'El chat está desactivado o necesita un WhatsApp de destino válido.' }}</p>
            </div>
            <div class="assistant-settings-badges">
                <x-filament::badge :color="$isPubliclyAvailable ? 'success' : 'gray'">{{ $isPubliclyAvailable ? 'Activo' : 'Inactivo' }}</x-filament::badge>
                <x-filament::badge color="gray">{{ $activeQuestions }} preguntas activas</x-filament::badge>
            </div>
        </div>

        @if(! $canUpdate)
            <p class="assistant-settings-notice" role="status">Tienes acceso de consulta. Puedes simular la configuración guardada, pero no modificarla.</p>
        @endif
        @if(! $conversationReady)
            <p class="assistant-settings-notice" role="status">Falta activar la actualización de preguntas y foto. El responsable de la instalación debe aplicar las dos migraciones de conversación; la configuración actual sigue disponible.</p>
        @endif

        @if($previewHtml)
            <section class="assistant-settings-preview" aria-labelledby="assistant-preview-title">
                <div class="assistant-settings-preview-heading">
                    <div>
                        <h2 id="assistant-preview-title">Simulador del chat</h2>
                        <p>Prueba tus preguntas sin guardar consultas ni abrir WhatsApp.</p>
                    </div>
                    <x-filament::button type="button" color="gray" wire:click="closePreview" wire:loading.attr="disabled" icon="heroicon-o-x-mark">Cerrar simulador</x-filament::button>
                </div>
                <iframe title="Simulador del asistente de contacto" wire:key="contact-assistant-preview-{{ $previewRevision }}" srcdoc="{{ $previewHtml }}" sandbox="allow-scripts allow-same-origin" x-init="$el.closest('section').scrollIntoView({ block: 'start' })"></iframe>
            </section>
        @endif

        <form wire:submit="save" class="assistant-settings-form">
            @if($errors->any())
                <div class="assistant-settings-errors" role="alert" tabindex="-1">
                    <strong>Revisa la configuración antes de continuar</strong>
                    <ul>@foreach(array_unique($errors->all()) as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif

            {{ $this->form }}

            <div class="assistant-settings-savebar">
                <p>{{ $canUpdate ? 'Guardar aplica los cambios al chat público. Simular solo prueba la configuración.' : 'La configuración guardada se muestra en modo consulta.' }}</p>
                <div class="assistant-settings-save-actions">
                    <x-filament::button type="button" color="gray" wire:click="reloadSettings" wire:loading.attr="disabled" aria-label="Recargar ajustes guardados"><span class="assistant-settings-label-full">Recargar guardados</span><span class="assistant-settings-label-short">Recargar</span></x-filament::button>
                    @if($canUpdate)
                        <x-filament::button type="submit" wire:loading.attr="disabled" icon="heroicon-o-check" aria-label="Guardar configuración"><span class="assistant-settings-label-full">Guardar configuración</span><span class="assistant-settings-label-short">Guardar</span></x-filament::button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
