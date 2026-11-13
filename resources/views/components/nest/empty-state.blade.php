@props(['title', 'icon' => 'sparkles'])

<div {{ $attributes->class('nest-card flex flex-col items-center gap-2 px-6 py-12 text-center') }}>
    <span class="mb-2 grid size-14 place-items-center rounded-2xl bg-accent-soft text-accent">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-7" />
    </span>
    <p class="font-semibold">{{ $title }}</p>
    <p class="max-w-xs text-sm text-muted">{{ $slot }}</p>
</div>
