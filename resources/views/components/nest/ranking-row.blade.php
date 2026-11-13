@props(['tool', 'position', 'href' => '#'])

<a href="{{ $href }}" {{ $attributes->class('nest-pressable flex items-center gap-3 rounded-2xl p-2 hover:bg-raised') }}>
    <span @class([
        'nest-num grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold',
        'bg-amber-400/15 text-amber-500' => $position === 1,
        'bg-raised text-muted' => $position !== 1,
    ])>{{ $position }}</span>
    <x-nest.app-logo :tool="$tool" size="size-11" />
    <span class="min-w-0 flex-1">
        <span class="block truncate font-semibold">{{ $tool->name }}</span>
        <span class="block truncate text-xs text-muted">{{ $tool->pivot?->best_for ?? $tool->tagline }}</span>
    </span>
    <x-heroicon-o-chevron-right class="size-5 shrink-0 text-muted" />
</a>
