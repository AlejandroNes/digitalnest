<x-filament::section
    :icon="\Filament\Support\Icons\Heroicon::OutlinedBolt"
    heading="Accesos rápidos"
    description="Acciones administrativas frecuentes"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach ($actions as $action)
            <a
                href="{{ $action['url'] }}"
                class="group flex flex-col justify-between p-4 rounded-xl border border-slate-200/90 dark:border-white/10 bg-white dark:bg-white/[0.03] hover:border-primary-500/80 dark:hover:border-primary-500/80 hover:bg-slate-50/70 dark:hover:bg-white/[0.06] hover:shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            >
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-200 group-hover:bg-primary-50 dark:group-hover:bg-primary-950/60 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        <x-filament::icon :icon="$action['icon']" class="size-5" />
                    </span>
                    <x-filament::icon icon="heroicon-m-arrow-up-right" class="size-4 text-slate-400 group-hover:text-primary-500 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" />
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        {{ $action['label'] }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                        {{ $action['description'] }}
                    </p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament::section>
