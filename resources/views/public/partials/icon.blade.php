@php($size = $size ?? 18)
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('check')
            <path d="M4.5 12.5l5 5L19.5 7"/>
            @break
        @case('minus')
            <path d="M6 12h12"/>
            @break
        @case('x')
            <path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>
            @break
        @case('arrow')
            <path d="M5 12h14M13 6l6 6-6 6"/>
            @break
        @case('briefcase')
            <rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7M3 12.5h18"/>
            @break
        @case('ruler')
            <path d="M3.5 16.5l13-13 4 4-13 13z"/><path d="M7.5 12.5l1.8 1.8M10.5 9.5l1.8 1.8M13.5 6.5l1.8 1.8"/>
            @break
        @case('refresh')
            <path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>
            @break
        @case('copy')
            <rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6.5A2.5 2.5 0 0 0 13.5 4h-7A2.5 2.5 0 0 0 4 6.5v7A2.5 2.5 0 0 0 6.5 16H8"/>
            @break
        @case('download')
            <path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/>
            @break
    @endswitch
</svg>
