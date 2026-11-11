<span class="dn-logo {{ ($large ?? false) ? 'dn-logo--lg' : '' }}" aria-hidden="true">
    @if($tool->logoUrl())
        <x-content-image :media="$tool->logo" alt="" width="64" height="64" />
    @else
        {{ mb_strtoupper(mb_substr($tool->name, 0, 1)) }}
    @endif
</span>
