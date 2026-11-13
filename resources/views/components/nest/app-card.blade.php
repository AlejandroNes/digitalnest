@props(['tool', 'href' => '#'])

<a href="{{ $href }}" {{ $attributes->class('nest-card nest-pressable flex items-center gap-3 p-3 hover:border-accent/40') }}>
    <x-nest.app-logo :tool="$tool" />
    <span class="min-w-0 flex-1">
        <span class="block truncate font-semibold">{{ $tool->name }}</span>
        <span class="block truncate text-sm text-muted">{{ $tool->tagline }}</span>
    </span>
    @if ($tool->rating)
        <span class="nest-num flex shrink-0 items-center gap-1 text-sm font-semibold">
            <x-heroicon-s-star class="size-4 text-amber-400" />{{ $tool->rating }}
        </span>
    @endif
</a>
