<div x-data="{ target: @js($target) }"
     x-on:media-library-selection.window="if ($event.detail.target === target) $wire.set(@js($getContainer()->getStatePath().(($multiple ?? false) ? '.selected_media_ids' : '.selected_media_id')), @js($multiple ?? false) ? $event.detail.mediaIds : $event.detail.mediaId)">
    <p class="media-picker-help">Las imágenes que subas permanecerán en la biblioteca aunque canceles.</p>
    @error($getContainer()->getStatePath().(($multiple ?? false) ? '.selected_media_ids' : '.selected_media_id'))
        <p class="media-upload-error" role="alert">{{ $message }}</p>
    @enderror
</div>
