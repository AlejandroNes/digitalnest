<article class="dn-card">
    <div class="dn-card-top">
        @include('public.partials.logo', ['tool' => $tool])
        <div class="dn-card-title">
            <a class="dn-card-name" href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
            <span class="dn-card-category">{{ $tool->category?->name }}</span>
        </div>
        @include('public.partials.score', ['tool' => $tool])
    </div>
    <p class="dn-card-text">{{ $tool->tagline }}</p>
    <div class="dn-card-facts">
        <span class="dn-pill dn-pill--sky">{{ $tool->startingPriceLabel() ? 'Desde '.$tool->startingPriceLabel().'/mes' : $tool->pricing_model?->getLabel() }}</span>
        @if($tool->has_free_plan)
            <span class="dn-pill">Plan gratis</span>
        @endif
        @if($tool->spanish_support === \App\ToolSpanishSupport::Full)
            <span class="dn-pill">En español</span>
        @endif
        @if($tool->is_featured)
            <span class="dn-tag">Recomendada</span>
        @endif
    </div>
</article>
