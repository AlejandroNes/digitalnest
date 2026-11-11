<div class="grid grid-cols-1 {{ $canViewMessages && $canViewPosts ? 'lg:grid-cols-2' : '' }} gap-6">
    @if ($canViewMessages)
        <x-filament::section
            :icon="\Filament\Support\Icons\Heroicon::OutlinedInbox"
            heading="Últimos mensajes recibidos"
        >
            <x-slot name="afterHeader">
                <a
                    href="{{ \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl() }}"
                    class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 focus:outline-none focus:underline"
                >
                    Ver todos
                </a>
            </x-slot>

            @if ($recentMessages->isEmpty())
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="mx-auto size-8 text-gray-400 dark:text-gray-500 mb-2" />
                    <p class="font-medium text-gray-700 dark:text-gray-200">No hay mensajes de contacto</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Los mensajes recibidos a través del formulario aparecerán aquí.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5" role="list">
                    @foreach ($recentMessages as $message)
                        <li class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3.5 group">
                            <div class="size-8 rounded-full bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center justify-center font-semibold text-xs shrink-0 select-none">
                                {{ strtoupper(mb_substr($message->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('view', ['record' => $message]) }}"
                                        class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 truncate focus:outline-none focus:underline transition-colors"
                                    >
                                        {{ $message->name }}
                                    </a>
                                    @if ($message->status === \App\ContactMessageStatus::Pending)
                                        <x-filament::badge color="warning" size="sm">
                                            Pendiente
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray" size="sm">
                                            Leído
                                        </x-filament::badge>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                    {{ $message->subject ?: $message->email }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <time datetime="{{ $message->created_at?->toIso8601String() }}" class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                    {{ $message->created_at?->format('d/m/Y H:i') }}
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif

    @if ($canViewPosts)
        <x-filament::section
            :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentText"
            heading="Artículos recientes"
        >
            <x-slot name="afterHeader">
                <a
                    href="{{ \App\Filament\Resources\Posts\PostResource::getUrl() }}"
                    class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 focus:outline-none focus:underline"
                >
                    Ver todos
                </a>
            </x-slot>

            @if ($recentPosts->isEmpty())
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-newspaper" class="mx-auto size-8 text-gray-400 dark:text-gray-500 mb-2" />
                    <p class="font-medium text-gray-700 dark:text-gray-200">No hay artículos registrados</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Los artículos creados o editados aparecerán aquí.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5" role="list">
                    @foreach ($recentPosts as $post)
                        <li class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-3.5 group">
                            <div class="size-8 rounded-lg bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0">
                                <x-filament::icon icon="heroicon-m-document-text" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $post]) }}"
                                        class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 truncate focus:outline-none focus:underline transition-colors"
                                    >
                                        {{ $post->title }}
                                    </a>
                                    @if ($post->status === \App\PostStatus::Published)
                                        <x-filament::badge color="success" size="sm">
                                            Publicado
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray" size="sm">
                                            Borrador
                                        </x-filament::badge>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                    Actualizado {{ $post->updated_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <a
                                    href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $post]) }}"
                                    class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 inline-flex focus:outline-none focus:text-primary-600 transition-colors"
                                    aria-label="Editar {{ $post->title }}"
                                >
                                    <x-filament::icon icon="heroicon-m-pencil-square" class="size-4" />
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif
</div>
