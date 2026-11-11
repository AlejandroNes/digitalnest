<article>
    <x-content-image :media="$tool->logo" :alt="$tool->logo?->model?->alt_text ?? ''" width="48" height="48" />
    <h3><a href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a></h3>
    <p>{{ $tool->tagline }}</p>
    <p>
        <span>{{ $tool->pricing_model?->getLabel() }}</span>
        @if($tool->has_free_plan)
            · <span>Plan gratis</span>
        @endif
        @if($tool->category)
            · <span>{{ $tool->category->name }}</span>
        @endif
    </p>
</article>
