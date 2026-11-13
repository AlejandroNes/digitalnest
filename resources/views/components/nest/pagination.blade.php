@props(['paginator'])

@if ($paginator->hasPages())
    <nav aria-label="Paginación" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="h-11 rounded-full border border-line px-5 leading-11 text-sm text-muted opacity-50">Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="nest-pressable h-11 rounded-full border border-line bg-surface px-5 leading-11 text-sm font-medium">Anterior</a>
        @endif
        <span class="nest-num text-sm text-muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="nest-pressable h-11 rounded-full border border-line bg-surface px-5 leading-11 text-sm font-medium">Siguiente</a>
        @else
            <span class="h-11 rounded-full border border-line px-5 leading-11 text-sm text-muted opacity-50">Siguiente</span>
        @endif
    </nav>
@endif
