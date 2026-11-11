@php($record = $getRecord())
<div class="media-card">
    <div class="media-card-image" x-data="{ failed: false }">
        @if ($card['url'])
            <img src="{{ $card['url'] }}" alt="{{ $record->alt_text }}" loading="lazy" decoding="async" width="320" height="320" x-show="!failed" x-on:error="failed = true">
        @endif
            <div class="media-card-placeholder" @if($card['url']) x-cloak x-show="failed" @endif>
                <x-filament::icon icon="heroicon-o-photo" class="size-8" />
                <span>Miniatura no disponible</span>
            </div>
        @if ($selected !== null)
            <span @class(['media-card-selection', 'media-card-selection-active' => $selected]) aria-hidden="true">
                @if ($selected)<x-filament::icon icon="heroicon-m-check" class="size-4" />@endif
            </span>
        @endif
        @if ($card['used'])
            <span class="media-card-used">En uso</span>
        @endif
    </div>
    <div class="media-card-information">
        <p class="media-card-title" title="{{ $record->name }}">{{ $record->name }}</p>
        <p class="media-card-dimensions">{{ $card['dimensions'] ? $card['dimensions'].' px' : 'Dimensiones no registradas' }}</p>
        <p class="media-card-caption"><span>{{ $card['format'] }}</span><span>{{ $card['size'] }}</span></p>
        @if($selectionIssue ?? null)<p class="media-card-issue">{{ $selectionIssue }}</p>@endif
    </div>
</div>
