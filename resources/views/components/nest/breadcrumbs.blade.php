{{-- Schema.org BreadcrumbList; the visible trail is the back link of each page. --}}
@props(['items'])

@push('structured-data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect([['Inicio', url('/')], ...$items])->values()->map(fn (array $item, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item[0],
            'item' => $item[1],
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
