{{-- Local-only showcase of the DigitalNest design system with unsaved sample models. --}}
@extends('nest.layout')

@section('seo')
    <title>Sistema de diseño · DigitalNest</title>
    <meta name="robots" content="noindex">
@endsection

@php
    use App\DealType;
    use App\Models\Deal;
    use App\Models\Tool;
    use App\Models\ToolCategory;

    $tool = fn (string $name, string $tagline, int $rating): Tool => new Tool(['name' => $name, 'tagline' => $tagline, 'rating' => $rating]);
    $tools = [
        $tool('Midjourney', 'Imágenes con la mejor calidad estética', 9),
        $tool('Runway', 'Genera y edita vídeo con IA', 8),
        $tool('ElevenLabs', 'Voces realistas en 30 idiomas', 9),
        $tool('Hostinger', 'Hosting rápido y barato para empezar', 7),
    ];
    $deal = function (Tool $tool, array $attributes): Deal {
        $deal = new Deal(['currency' => 'USD', ...$attributes]);
        $deal->setRelation('tool', $tool);

        return $deal;
    };
    $deals = [
        $deal($tools[3], ['type' => DealType::Coupon, 'title' => 'Plan Premium 3 meses gratis + dominio', 'code' => 'NEST20', 'discount_percent' => 78, 'deal_price' => 2.99, 'original_price' => 13.99, 'expires_at' => now()->addDays(2)]),
        $deal($tools[2], ['type' => DealType::Discount, 'title' => 'Starter a mitad de precio el primer mes', 'discount_percent' => 50, 'deal_price' => 2.5, 'original_price' => 5, 'expires_at' => now()->addDays(20)]),
        $deal($tools[1], ['type' => DealType::FreeTrial, 'title' => '125 créditos gratis para probar Gen-4', 'expires_at' => null]),
    ];
    $sectors = collect([
        ['Inteligencia artificial', 'sparkles', '#8B5CF6'], ['Vídeo', 'film', '#EF4444'], ['Audio', 'musical-note', '#F59E0B'],
        ['Imágenes', 'photo', '#EC4899'], ['Hosting', 'server-stack', '#3B82F6'], ['VPN', 'shield-check', '#22C55E'],
    ])->map(fn (array $row): ToolCategory => new ToolCategory(['name' => $row[0], 'icon' => $row[1], 'accent_color' => $row[2]]));
@endphp

@section('content')
    <div class="flex flex-col gap-8 lg:gap-10">
        <section class="relative overflow-hidden rounded-sheet border border-line bg-surface p-5 shadow-card lg:p-8">
            <div class="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-accent/30 blur-3xl"></div>
            <div class="relative flex flex-col gap-4 lg:max-w-xl">
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-hot/15 px-3 py-1 text-xs font-semibold text-hot">
                    <x-heroicon-s-fire class="size-4" /> Oferta del día
                </span>
                <h1 class="text-[28px] leading-[1.1] font-bold tracking-tight lg:text-5xl">Las mejores apps, al mejor precio.</h1>
                <p class="text-muted">Cupones verificados y rankings honestos de IA, vídeo, audio, hosting y mucho más.</p>
                <div class="flex gap-2">
                    <a href="#" class="nest-pressable inline-flex h-12 items-center rounded-full bg-accent px-6 font-semibold text-accent-ink">Ver ofertas</a>
                    <a href="#" class="nest-pressable inline-flex h-12 items-center rounded-full border border-line bg-raised px-6 font-semibold">Explorar</a>
                </div>
            </div>
        </section>

        <div class="nest-scroller">
            <x-nest.chip active>Todas</x-nest.chip>
            <x-nest.chip><x-heroicon-o-ticket class="size-4" /> Cupones</x-nest.chip>
            <x-nest.chip>Pago único</x-nest.chip>
            <x-nest.chip>Gratis</x-nest.chip>
            <x-nest.chip><x-heroicon-o-clock class="size-4" /> Caducan pronto</x-nest.chip>
        </div>

        <x-nest.section title="Sectores" href="#">
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($sectors as $sector)
                    <x-nest.sector-tile :sector="$sector" />
                @endforeach
            </div>
        </x-nest.section>

        <x-nest.section title="Ofertas destacadas" href="#">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($deals as $item)
                    <x-nest.deal-card :deal="$item" />
                @endforeach
            </div>
        </x-nest.section>

        <div class="grid gap-8 lg:grid-cols-2">
            <x-nest.section title="Top IA de la semana" href="#">
                <div class="nest-card flex flex-col p-2">
                    @foreach ($tools as $index => $item)
                        <x-nest.ranking-row :tool="$item" :position="$index + 1" />
                    @endforeach
                </div>
            </x-nest.section>

            <x-nest.section title="Apps populares">
                <div class="flex flex-col gap-3">
                    @foreach (array_slice($tools, 0, 3) as $item)
                        <x-nest.app-card :tool="$item" />
                    @endforeach
                </div>
            </x-nest.section>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <x-nest.skeleton />
            <x-nest.empty-state title="Aún no tienes guardados" icon="bookmark">Toca el marcador de una oferta para tenerla a mano.</x-nest.empty-state>
        </div>
    </div>
@endsection
