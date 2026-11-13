@extends('nest.layout')

@section('seo')
    <x-seo title="Rankings de las mejores apps · DigitalNest" description="Listas actualizadas de las mejores apps por sector: IA, vídeo, audio, hosting, VPN y más." :noindex="$rankings->currentPage() > 1" />
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header title="Rankings" subtitle="Las mejores apps de cada sector, probadas y ordenadas." />

        @if ($rankings->isEmpty())
            <x-nest.empty-state title="Aún no hay rankings" icon="trophy">Estamos preparando las primeras listas.</x-nest.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($rankings as $ranking)
                    <a href="{{ route('rankings.show', ['slug' => $ranking->slug]) }}" class="nest-card nest-pressable flex flex-col gap-4 p-4 hover:border-accent/40">
                        <span class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-400/15 text-amber-500"><x-heroicon-o-trophy class="size-6" /></span>
                            <span class="min-w-0">
                                <span class="block leading-snug font-semibold">{{ $ranking->title }}</span>
                                <span class="text-sm text-muted">{{ $ranking->category?->name ?? 'General' }} · {{ $ranking->tools->count() }} apps</span>
                            </span>
                        </span>
                        @if ($ranking->tools->isNotEmpty())
                            <span class="flex -space-x-2">
                                @foreach ($ranking->tools->take(5) as $tool)
                                    <x-nest.app-logo :tool="$tool" size="size-9" class="rounded-xl text-xs ring-2 ring-surface" />
                                @endforeach
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
            <x-nest.pagination :paginator="$rankings" />
        @endif
    </div>
@endsection
