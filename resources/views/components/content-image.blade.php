@if($src)
    <img {{ $attributes->except(['src', 'srcset', 'width', 'height', 'alt', 'loading', 'fetchpriority', 'sizes', 'decoding'])->merge(['style' => 'max-width: 100%; height: auto;']) }}
        src="{{ $src }}" alt="{{ $alt }}" loading="{{ $priority ? 'eager' : 'lazy' }}" decoding="async"
        @if($priority) fetchpriority="high" @endif
        @if($width && $height) width="{{ $width }}" height="{{ $height }}" @endif
        @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif>
@endif
