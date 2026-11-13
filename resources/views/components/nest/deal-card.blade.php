@props(['deal'])

@php
    $tool = $deal->tool;
    $detailUrl = $deal->slug ? route('deals.show', ['slug' => $deal->slug]) : '#';
    $goUrl = $deal->slug ? route('deals.go', ['slug' => $deal->slug]) : '#';
    $expiresSoon = $deal->expires_at !== null && $deal->expires_at->between(now(), now()->addDays(3));
@endphp
<article {{ $attributes->class('nest-card relative flex flex-col gap-3 p-4 has-[a:focus-visible]:ring-2 has-[a:focus-visible]:ring-accent') }}>
    <div class="flex items-start gap-3">
        <x-nest.app-logo :tool="$tool" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium text-muted">{{ $tool->name }} · {{ $deal->type->getLabel() }}</p>
            <h3 class="mt-0.5 line-clamp-2 text-[15px] leading-snug font-semibold">
                <a href="{{ $detailUrl }}" class="after:absolute after:inset-0 after:rounded-card focus-visible:outline-none">{{ $deal->title }}</a>
            </h3>
        </div>
        @if ($deal->slug)
            <button type="button" data-save-deal="{{ $deal->slug }}" aria-pressed="false" aria-label="Guardar oferta"
                    class="nest-pressable group/save relative z-10 -m-1 grid size-9 shrink-0 place-items-center rounded-full text-muted hover:text-ink aria-pressed:text-accent">
                <x-heroicon-o-bookmark class="size-5 group-aria-pressed/save:hidden" />
                <x-heroicon-s-bookmark class="hidden size-5 group-aria-pressed/save:block" />
            </button>
        @endif
        @if ($deal->discount_percent)
            <span class="nest-num shrink-0 rounded-lg bg-deal-soft px-2 py-1 text-sm font-bold text-deal">−{{ $deal->discount_percent }}%</span>
        @endif
    </div>

    <div class="flex items-center gap-2 text-xs text-muted">
        @if ($deal->deal_price !== null)
            <span class="nest-num text-base font-semibold text-ink">{{ $deal->priceLabel($deal->deal_price) }}</span>
            @if ($deal->original_price)
                <span class="nest-num line-through">{{ $deal->priceLabel($deal->original_price) }}</span>
            @endif
        @endif
        @if ($deal->expires_at)
            <span @class(['ml-auto inline-flex items-center gap-1', 'font-medium text-hot' => $expiresSoon])>
                <x-heroicon-o-clock class="size-4" />
                {{ $expiresSoon ? 'Caduca '.$deal->expires_at->diffForHumans() : 'Hasta '.$deal->expires_at->translatedFormat('j M') }}
            </span>
        @endif
    </div>

    @if ($deal->hasCode())
        <button type="button" data-copy-code="{{ $deal->code }}"
                class="nest-pressable relative z-10 flex h-11 items-center justify-between rounded-xl border border-dashed border-accent/50 bg-accent-soft px-4 font-mono text-sm font-semibold tracking-wider text-accent">
            {{ $deal->code }}
            <span class="flex items-center gap-1 font-sans text-xs tracking-normal"><x-heroicon-o-clipboard-document class="size-4" /> Copiar</span>
        </button>
    @else
        <a href="{{ $goUrl }}" target="_blank" rel="sponsored nofollow noopener"
           class="nest-pressable relative z-10 flex h-11 items-center justify-center gap-1.5 rounded-xl bg-accent text-sm font-semibold text-accent-ink">
            Ver oferta <x-heroicon-o-arrow-up-right class="size-4" />
        </a>
    @endif
</article>
