@props(['href' => '#', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="true" @endif
   {{ $attributes->class('nest-pressable inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-line bg-surface px-3.5 text-sm font-medium text-muted hover:text-ink aria-[current=true]:border-transparent aria-[current=true]:bg-ink aria-[current=true]:text-bg') }}>
    {{ $slot }}
</a>
