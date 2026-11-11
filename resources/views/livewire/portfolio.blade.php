<section
    aria-labelledby="portfolio-heading"
    x-data="{
        scrollState: null,
        opener: null,
        syncDialog(id) {
            this.$nextTick(() => {
                if (id && !this.$refs.dialog.open) {
                    this.lockScroll();
                    this.$refs.dialog.showModal();
                    this.$refs.dialog.scrollTop = 0;
                } else if (!id && this.$refs.dialog.open) {
                    this.$refs.dialog.close();
                    this.unlockScroll();
                }
            });
        },
        lockScroll() {
            if (this.scrollState) return;
            const body = document.body;
            this.scrollState = {
                y: window.scrollY,
                position: body.style.position,
                top: body.style.top,
                width: body.style.width,
                overflow: body.style.overflow,
                rootOverflow: document.documentElement.style.overflow
            };
            body.style.position = 'fixed';
            body.style.top = '-' + this.scrollState.y + 'px';
            body.style.width = '100%';
            body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        },
        unlockScroll() {
            if (!this.scrollState) return;
            const state = this.scrollState;
            Object.assign(document.body.style, { position: state.position, top: state.top, width: state.width, overflow: state.overflow });
            document.documentElement.style.overflow = state.rootOverflow;
            this.scrollState = null;
            window.scrollTo(0, state.y);
            if (this.opener && this.opener.isConnected) this.opener.focus({ preventScroll: true });
        },
        closeDialog() {
            this.$refs.dialog.close();
            this.unlockScroll();
        },
        destroy() { this.unlockScroll(); }
    }"
    x-effect="syncDialog($wire.selectedProjectId)"
>
    <header class="mb-8 sm:mb-10">
        <h1 id="portfolio-heading" class="text-3xl font-semibold tracking-tight sm:text-4xl">Nuestro portafolio</h1>
        <p class="mt-3 max-w-2xl text-base leading-relaxed text-gray-600 sm:text-lg">Conoce algunos de los trabajos que hemos realizado.</p>
    </header>

    <nav aria-label="Categorías del portafolio" class="mb-8 flex flex-wrap gap-2">
        <a href="{{ route('projects.index') }}" wire:click.prevent="filterByCategory('')"
            @if($category === '') aria-current="true" @endif
            @class(['portfolio-filter', 'portfolio-filter-active' => $category === ''])>Todos</a>
        @foreach($categories as $item)
            <a href="{{ route('projects.index', ['categoria' => $item->slug]) }}"
                wire:key="portfolio-category-{{ $item->id }}"
                wire:click.prevent="filterByCategory({{ \Illuminate\Support\Js::from($item->slug) }})"
                @if($category === $item->slug) aria-current="true" @endif
                @class(['portfolio-filter', 'portfolio-filter-active' => $category === $item->slug])>{{ $item->name }}</a>
        @endforeach
    </nav>

    <p wire:loading wire:target="filterByCategory, gotoPage, nextPage, previousPage" role="status" class="mb-4 text-sm text-gray-600">Cargando trabajos…</p>
    <div wire:loading.class="opacity-60" wire:target="filterByCategory, gotoPage, nextPage, previousPage">
        @if($projects->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center">
                <p class="text-gray-600">No hay trabajos publicados{{ $category !== '' ? ' en esta categoría' : '' }}.</p>
                @if($category !== '')
                    <button type="button" wire:click="filterByCategory('')" class="portfolio-filter mt-5">Ver todos los trabajos</button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($projects as $project)
                    <article wire:key="portfolio-project-{{ $project->id }}" class="min-w-0">
                        <button type="button" class="portfolio-card" wire:click="openProject({{ $project->id }})"
                            x-on:click="opener = $event.currentTarget" wire:loading.attr="disabled" wire:target="openProject"
                            aria-haspopup="dialog" aria-label="Ver detalles de {{ $project->title }}">
                            <span class="portfolio-card-image">
                                @if($project->imageUrl())
                                    <x-content-image :media="$project->image" :alt="$project->imageAlt() ?? $project->title"
                                        sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw" />
                                @else
                                    <span class="portfolio-image-fallback" aria-hidden="true"><x-heroicon-o-photo class="size-10" /></span>
                                @endif
                            </span>
                            <span class="block px-5 py-5">
                                <span class="block break-words text-lg font-semibold leading-snug">{{ $project->title }}</span>
                                @if($project->category)
                                    <span class="mt-2 block break-words text-sm text-gray-600">{{ $project->category->name }}</span>
                                @endif
                            </span>
                        </button>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">{{ $projects->links(data: ['scrollTo' => '#portfolio-heading']) }}</div>
        @endif
    </div>
    <p wire:loading wire:target="openProject" role="status" class="mt-4 text-sm text-gray-600">Abriendo proyecto…</p>

    <dialog x-ref="dialog" class="portfolio-dialog" aria-labelledby="portfolio-dialog-title"
        x-on:cancel.prevent="closeDialog()" x-on:close="unlockScroll(); if ($wire.selectedProjectId) $wire.closeProject()"
        x-on:click="if ($event.target === $el) closeDialog()" wire:key="portfolio-dialog">
        @if($selectedProject)
            <div class="portfolio-dialog-toolbar">
                <button type="button" autofocus x-on:click.stop="closeDialog()" aria-label="Cerrar detalles del proyecto" class="portfolio-dialog-close">
                    <x-heroicon-o-x-mark class="size-5" />
                </button>
            </div>
            <div class="overflow-hidden rounded-2xl bg-white" wire:key="portfolio-detail-{{ $selectedProject->id }}">
                <div class="portfolio-detail-image">
                    @if($selectedProject->imageUrl())
                        <x-content-image :media="$selectedProject->image" :alt="$selectedProject->imageAlt() ?? $selectedProject->title" :priority="true" sizes="(min-width: 768px) 768px, 100vw" />
                    @else
                        <span class="portfolio-image-fallback" aria-hidden="true"><x-heroicon-o-photo class="size-12" /></span>
                    @endif
                </div>
                <div class="space-y-5 p-6 sm:p-8">
                    <div>
                        <h2 id="portfolio-dialog-title" class="break-words text-2xl font-semibold leading-tight tracking-tight sm:text-3xl">{{ $selectedProject->title }}</h2>
                        @if($selectedProject->category)
                            <p class="mt-3 text-sm text-indigo-700">{{ $selectedProject->category->name }}</p>
                        @endif
                    </div>
                    @if($selectedProject->portfolioDescription() !== '')
                        <p class="whitespace-pre-line break-words text-base leading-relaxed text-gray-600">{{ $selectedProject->portfolioDescription() }}</p>
                    @endif
                    @if(filled($selectedProject->client) || $selectedProject->completed_at)
                        <dl class="grid grid-cols-1 gap-5 border-t border-gray-200 pt-5 sm:grid-cols-2">
                            @if(filled($selectedProject->client))
                                <div><dt class="text-sm text-gray-500">Cliente / Empresa</dt><dd class="mt-1 break-words font-medium">{{ $selectedProject->client }}</dd></div>
                            @endif
                            @if($selectedProject->completed_at)
                                <div><dt class="text-sm text-gray-500">Fecha de realización</dt><dd class="mt-1 font-medium"><time datetime="{{ $selectedProject->completed_at->format('Y-m-d') }}">{{ $selectedProject->completed_at->format('d/m/Y') }}</time></dd></div>
                            @endif
                        </dl>
                    @endif
                    @if($selectedProject->safeExternalUrl())
                        <a href="{{ $selectedProject->safeExternalUrl() }}" target="_blank" rel="noopener noreferrer" class="portfolio-visit">Visitar proyecto <x-heroicon-o-arrow-up-right class="size-4" aria-hidden="true" /></a>
                    @endif
                </div>
            </div>
        @endif
    </dialog>
</section>
