<li class="dn-item dn-item--split">
    <div>
        <a class="dn-item-title" href="{{ route('workflows.show', ['slug' => $workflow->slug]) }}">{{ $workflow->title }}</a>
        @if($workflow->is_featured)
            <span class="dn-tag">Recomendado</span>
        @endif
        <p class="dn-tool-tagline">{{ $workflow->summary }}</p>
    </div>
    <p class="dn-meta">
        <span class="dn-pill dn-pill--sky">{{ $workflow->durationLabel() }}</span>
        <span class="dn-pill">{{ $workflow->costLabel() }}</span>
        <span class="dn-pill">{{ $workflow->difficulty?->getLabel() }}</span>
        @isset($workflow->steps_count)
            <span class="dn-pill">{{ trans_choice(':count paso|:count pasos', $workflow->steps_count, ['count' => $workflow->steps_count]) }}</span>
        @endisset
    </p>
</li>
