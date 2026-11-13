@props(['tool', 'size' => 'size-12'])

@php($logo = $tool->logoUrl())
<span {{ $attributes->class([$size, 'grid shrink-0 place-items-center overflow-hidden rounded-2xl border border-line bg-raised text-base font-semibold text-ink']) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="" class="size-full object-cover" loading="lazy" decoding="async">
    @else
        {{ mb_strtoupper(mb_substr($tool->name, 0, 1)) }}
    @endif
</span>
