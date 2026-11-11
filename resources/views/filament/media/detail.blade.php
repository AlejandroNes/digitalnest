<div class="media-detail">
    <div class="media-detail-preview">
        @if ($detail['preview_url'])
            <img src="{{ $detail['preview_url'] }}" alt="{{ $detail['alt_text'] }}" decoding="async">
        @else
            <div class="media-card-placeholder">
                <x-filament::icon icon="heroicon-o-photo" class="size-10" />
                <p>Imagen no disponible</p>
            </div>
        @endif
    </div>
    <div class="media-detail-information">
        @if ($detail['original_missing'])
            <p class="media-detail-warning" role="status">El archivo original no está disponible. Revisa el almacenamiento antes de reutilizar esta imagen.</p>
        @endif
        <dl>
            <div><dt>Título</dt><dd>{{ $detail['name'] }}</dd></div>
            <div><dt>Texto alternativo (ALT)</dt><dd>{{ $detail['alt_text'] }}</dd></div>
            <div><dt>Nombre original</dt><dd>{{ $detail['original_filename'] }}</dd></div>
            <div><dt>Archivo almacenado</dt><dd>{{ $detail['filename'] }}</dd></div>
            <div><dt>Formato y tamaño</dt><dd>{{ $detail['format'] }} · {{ $detail['size'] }}</dd></div>
            <div><dt>Dimensiones originales</dt><dd>{{ $detail['dimensions'] ?? 'No registradas' }}</dd></div>
            <div><dt>Fecha de carga</dt><dd>{{ $detail['created_at'] }}</dd></div>
            <div><dt>Utilizada en</dt><dd>{{ $detail['usages'] ? implode(', ', $detail['usages']) : 'Sin referencias conocidas' }}</dd></div>
        </dl>
        @if ($detail['original_url'])
            <div class="media-detail-url">
                <label for="media-original-url">URL del original</label>
                <input id="media-original-url" type="text" readonly value="{{ $detail['original_url'] }}" class="fi-input" x-on:click="$el.select()">
                <a href="{{ $detail['original_url'] }}" target="_blank" rel="noopener noreferrer" class="media-original-link">Abrir original en otra pestaña</a>
            </div>
        @endif
    </div>
</div>
