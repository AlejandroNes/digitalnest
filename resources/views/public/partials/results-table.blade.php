{{-- Expects $tools (iterable of Tool) and $caption (accessible table name). --}}
<table class="dn-results" aria-label="{{ $caption }}">
    <thead>
        <tr>
            <th scope="col">Herramienta</th>
            <th scope="col">Nota</th>
            <th scope="col">Precio</th>
            <th scope="col">Plan gratis</th>
            <th scope="col">Español</th>
        </tr>
    </thead>
    <tbody>
        @foreach($tools as $tool)
            <tr>
                <td data-cell="tool">
                    <div class="dn-tool">
                        @include('public.partials.logo', ['tool' => $tool])
                        <div>
                            <a class="dn-tool-name" href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
                            @if($tool->is_featured)
                                <span class="dn-tag">Recomendada</span>
                            @endif
                            <p class="dn-tool-tagline">{{ $tool->tagline }}</p>
                        </div>
                    </div>
                </td>
                <td data-cell="score">@include('public.partials.score', ['tool' => $tool, 'withLabel' => true])</td>
                <td data-cell="fact" data-label="Precio">
                    @if($tool->startingPriceLabel())
                        desde {{ $tool->startingPriceLabel() }}<span class="dn-soft">/mes</span>
                    @else
                        {{ $tool->pricing_model?->getLabel() }}
                    @endif
                </td>
                <td data-cell="fact" data-label="Plan gratis">
                    @include('public.partials.mark', ['state' => $tool->has_free_plan ? 'yes' : 'no', 'text' => $tool->has_free_plan ? 'Sí' : 'No'])
                </td>
                <td data-cell="fact" data-label="Español">
                    @include('public.partials.mark', [
                        'state' => match ($tool->spanish_support) { \App\ToolSpanishSupport::Full => 'yes', \App\ToolSpanishSupport::Partial => 'partial', default => 'no' },
                        'text' => match ($tool->spanish_support) { \App\ToolSpanishSupport::Full => 'Bien', \App\ToolSpanishSupport::Partial => 'Parcial', default => 'Solo inglés' },
                    ])
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
