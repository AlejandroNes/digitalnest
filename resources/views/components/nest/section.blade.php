@props(['title', 'href' => null, 'action' => 'Ver todo'])

<section {{ $attributes->class('flex min-w-0 flex-col gap-3') }}>
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-lg font-semibold tracking-tight lg:text-xl">{{ $title }}</h2>
        @if ($href)
            <a href="{{ $href }}" class="text-sm font-medium text-accent hover:underline">{{ $action }}</a>
        @endif
    </div>
    {{ $slot }}
</section>
