@php($verdict = $tool->verdict())
@if($verdict)
    <span class="dn-tone-{{ $verdict['tone'] }}">
        <span class="dn-score" aria-label="Nota {{ $tool->rating }} de 10, {{ mb_strtolower($verdict['label']) }}">{{ $tool->rating }}<small>/10</small></span>
        @if($withLabel ?? false)
            <span class="dn-score-label" aria-hidden="true">{{ $verdict['label'] }}</span>
        @endif
    </span>
@endif
