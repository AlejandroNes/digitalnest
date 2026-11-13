@props(['title', 'subtitle' => null, 'back' => null])

<header {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($back)
        <a href="{{ $back['url'] }}" class="mb-1 inline-flex w-fit items-center gap-1 text-sm font-medium text-muted hover:text-ink">
            <x-heroicon-o-chevron-left class="size-4" /> {{ $back['label'] }}
        </a>
    @endif
    <h1 class="text-[26px] leading-tight font-bold tracking-tight lg:text-4xl">{{ $title }}</h1>
    @if ($subtitle)
        <p class="max-w-2xl text-muted">{{ $subtitle }}</p>
    @endif
    {{ $slot }}
</header>
