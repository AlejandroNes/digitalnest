<x-filament-panels::page>
    @if ($this->mediaUploadResults)
        <x-filament::section heading="Resultado de la carga" aria-live="polite">
            <ul class="media-upload-results">
                @foreach ($this->mediaUploadResults as $result)
                    <li>
                        <span class="media-upload-filename">{{ $result['filename'] }}</span>
                        <span @class(['media-upload-status', 'media-upload-failed' => $result['status'] === 'failed'])>
                            {{ match ($result['status']) { 'created' => 'Guardada', 'existing' => 'Ya estaba en la biblioteca', default => 'No guardada' } }}
                        </span>
                        @foreach ($result['errors'] as $error)
                            <p class="media-upload-error">{{ $error }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
    {{ $this->content }}
</x-filament-panels::page>
