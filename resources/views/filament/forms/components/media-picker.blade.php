<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php($selected = $field->selectedMedia())
    @php($guidelines = $field->imageGuidelines())
    <div class="media-picker-field">
        @if($selected)
            @php($thumbnail = \App\Models\MediaAsset::mediaUrl($selected, 'thumb'))
            @if($thumbnail)
                <div @class(['media-picker-preview-frame', 'media-picker-crop' => $guidelines['crop']]) style="aspect-ratio: {{ $guidelines['width'] }} / {{ $guidelines['height'] }}" x-data="{ failed: false }">
                    <img src="{{ $thumbnail }}" alt="" width="{{ $guidelines['width'] }}" height="{{ $guidelines['height'] }}" class="media-picker-thumbnail" x-show="!failed" x-on:error="failed = true">
                    <span x-cloak x-show="failed" class="media-picker-missing">Imagen no disponible</span>
                </div>
            @endif
            <div class="media-picker-caption">
                <strong>{{ $selected->model?->name ?? $selected->file_name }}</strong>
                <span>{{ $selected->file_name }}</span>
                @php($dimensions = $selected->getCustomProperty('dimensions.original'))
                <span class="media-picker-properties">
                    @if(isset($dimensions['width'], $dimensions['height']))
                        {{ $dimensions['width'] }} × {{ $dimensions['height'] }} px ·
                    @endif
                    {{ strtoupper(str_replace('image/', '', $selected->mime_type)) }} · {{ \Illuminate\Support\Number::fileSize($selected->size, precision: 1) }}
                </span>
            </div>
        @else
            <div class="media-picker-empty-icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-photo" class="size-6" /></div>
            <span class="media-picker-caption">{{ filled($getState()) ? 'La imagen seleccionada no está disponible para consultar.' : 'Sin imagen seleccionada' }}</span>
        @endif
        <div class="media-picker-buttons">
            {{ $getAction('chooseImage') }}
            @if($getAction('clearImage')->isVisible()){{ $getAction('clearImage') }}@endif
        </div>
    </div>
    @include('filament.media.guidelines', ['guidelines' => $guidelines])
</x-dynamic-component>
