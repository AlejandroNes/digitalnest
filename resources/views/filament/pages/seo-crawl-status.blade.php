<div class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6"
    x-data="{ copied: false, copyError: false, async copyUrl(url) {
        try { await navigator.clipboard.writeText(url); this.copied = true; this.copyError = false; }
        catch (error) { this.copyError = true; }
    } }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $kind === 'sitemap' ? 'Sitemap XML' : 'Robots.txt' }}</h2>
        <x-filament::badge :color="$active ? 'success' : 'gray'">{{ $active ? ($kind === 'robots' ? 'Disponible' : 'Activo') : 'Desactivado' }}</x-filament::badge>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $message }}</p>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-950 dark:text-white" for="seo-{{ $kind }}-url">URL pública</label>
        <x-filament::input.wrapper>
            <x-filament::input id="seo-{{ $kind }}-url" type="url" readonly :value="$url" x-on:focus="$el.select()" />
        </x-filament::input.wrapper>
    </div>
    <div class="flex flex-wrap gap-3">
        @if($active)
            <x-filament::button tag="a" :href="$url" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square">{{ $kind === 'sitemap' ? 'Ver sitemap' : 'Ver robots.txt' }}</x-filament::button>
        @endif
        <x-filament::button color="gray" type="button" icon="heroicon-o-clipboard"
            x-on:click="copyUrl({{ \Illuminate\Support\Js::from($url) }})">
            Copiar URL
        </x-filament::button>
    </div>
    <p x-cloak x-show="copied && !copyError" role="status" class="text-sm text-gray-600 dark:text-gray-400">URL copiada.</p>
    <p x-cloak x-show="copyError" role="status" class="text-sm text-gray-600 dark:text-gray-400">Selecciona la URL y cópiala manualmente: el navegador no permitió copiarla.</p>
    @if($updated)
        <p class="text-sm text-gray-500 dark:text-gray-400">Última generación: {{ \Illuminate\Support\Carbon::parse($updated)->format('d/m/Y H:i') }}. Se actualiza automáticamente al cambiar contenido público.</p>
    @endif
    @if($sitemapUrl)
        <p class="break-all text-sm text-gray-600 dark:text-gray-400">Referencia del sitemap: {{ $sitemapUrl }}</p>
    @endif
</div>
