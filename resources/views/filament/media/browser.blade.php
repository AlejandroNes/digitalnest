<div class="media-picker-browser">
    <details class="media-picker-guidance">
        <summary>
            <span>{{ $guidelines['label'] }}</span>
            <strong>Recomendado: {{ $guidelines['width'] }} × {{ $guidelines['height'] }} px</strong>
            <span class="media-image-ratio">{{ $guidelines['ratio'] }}</span>
        </summary>
        <p>{{ $guidelines['description'] }} {{ $allowedFormats }}, máximo {{ $maxKilobytes }} KB por imagen.</p>
    </details>
    <div class="media-selection-status" role="status" aria-live="polite">
        <strong>{{ $multiple ? (count($selectedMediaIds) === 1 ? '1 imagen seleccionada' : count($selectedMediaIds).' imágenes seleccionadas') : ($selectedMediaId ? 'Imagen seleccionada' : 'Selecciona una imagen') }}</strong>
        <span>{{ $multiple ? 'Hasta '.\App\Livewire\MediaLibraryBrowser::MAX_SELECTION.' por selección. Se añadirán en el orden elegido.' : 'Confirma la selección para usarla en el formulario.' }}</span>
        @if($selectedMediaId || $selectedMediaIds)
            <button type="button" wire:click="clearSelection" wire:loading.attr="disabled" class="media-selection-clear">{{ $multiple ? 'Vaciar selección' : 'Quitar selección' }}</button>
        @endif
    </div>
    @if($selectedImages->isNotEmpty())
        <ol class="media-selection-list" aria-label="Imágenes elegidas">
            @foreach($selectedImages as $image)
                <li wire:key="selected-media-{{ $image->id }}" @class(['media-selection-single' => ! $multiple])>
                    @if($multiple)<span class="media-selection-order">{{ $loop->iteration }}</span>@endif
                    @php($thumbnail = \App\Filament\Support\MediaLibraryTable::thumbnailUrl($image))
                    <div @class(['media-selection-thumbnail', 'media-picker-crop' => $guidelines['crop']]) style="aspect-ratio: {{ $guidelines['width'] }} / {{ $guidelines['height'] }}" x-data="{ failed: false }">
                        @if($thumbnail)
                            <img src="{{ $thumbnail }}" alt="" class="media-picker-thumbnail" x-show="!failed" x-on:error="failed = true">
                        @endif
                        <x-filament::icon @if($thumbnail) x-cloak x-show="failed" @endif icon="heroicon-o-photo" class="size-4" />
                    </div>
                    <div class="media-selection-name">
                        <span>{{ $image->model?->name ?? $image->file_name }}</span>
                        @if(! $multiple)
                            @php($dimensions = $image->getCustomProperty('dimensions.original'))
                            <small>
                                @if(isset($dimensions['width'], $dimensions['height'])) {{ $dimensions['width'] }} × {{ $dimensions['height'] }} px · @endif
                                {{ \Illuminate\Support\Number::fileSize($image->size, precision: 1) }}
                            </small>
                            <small>{{ $guidelines['crop'] ? 'Vista previa del recorte centrado' : 'Vista previa sin recorte' }}</small>
                        @endif
                    </div>
                    @if($multiple)
                        <button type="button" wire:click="removeSelection({{ $image->id }})" wire:loading.attr="disabled" class="media-selection-remove" aria-label="Quitar {{ $image->model?->name ?? $image->file_name }} de la selección"><x-filament::icon icon="heroicon-m-x-mark" class="size-4" /></button>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
    @error('image') <p class="media-upload-error" role="alert">{{ $message }}</p> @enderror
    @if($mediaUploadResults)
        <section class="media-picker-upload-results" aria-label="Resultado de la carga" aria-live="polite">
            <ul class="media-upload-results">
                @foreach($mediaUploadResults as $result)
                    <li>
                        <span class="media-upload-filename">{{ $result['filename'] }}</span>
                        <span @class(['media-upload-status', 'media-upload-failed' => $result['status'] === 'failed'])>{{ match($result['status']) { 'created' => 'Guardada', 'existing' => 'Ya estaba en la biblioteca', default => 'No guardada' } }}</span>
                        @if($result['asset_id'] ?? null)
                            <x-filament::button type="button" color="gray" wire:click="selectMedia({{ $result['asset_id'] }})" wire:loading.attr="disabled" aria-label="Seleccionar {{ $result['filename'] }}">Seleccionar</x-filament::button>
                        @endif
                        @foreach($result['errors'] as $error)<p class="media-upload-error">{{ $error }}</p>@endforeach
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
