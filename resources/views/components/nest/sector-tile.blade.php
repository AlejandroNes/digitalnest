@props(['sector', 'href' => '#'])

<a href="{{ $href }}" style="--sector: {{ $sector->accent_color ?? 'var(--nest-accent)' }}"
   {{ $attributes->class('nest-card nest-pressable flex flex-col gap-3 p-4 hover:border-[color-mix(in_oklab,var(--sector)_40%,transparent)]') }}>
    <span class="grid size-11 place-items-center rounded-2xl bg-[color-mix(in_oklab,var(--sector)_16%,transparent)] text-(--sector)">
        <x-dynamic-component :component="'heroicon-o-'.($sector->icon ?: 'squares-2x2')" class="size-6" />
    </span>
    <span class="text-sm leading-tight font-semibold">{{ $sector->name }}</span>
</a>
