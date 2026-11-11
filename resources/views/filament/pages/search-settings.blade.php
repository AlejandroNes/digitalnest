<x-filament-panels::page>
    @if(!\App\Models\SearchSetting::supportsIntegrations())
        <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">La ampliación SEO está preparada. Aplica las dos migraciones pendientes para habilitar el guardado de integraciones, sitemap y código personalizado.</p>
    @endif
    {{ $this->form }}
</x-filament-panels::page>
