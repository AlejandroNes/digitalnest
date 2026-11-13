@extends('nest.layout')

@section('seo')
    @if($category)
        <x-seo :title="'Guías de '.$category->name" :description="'Guías y tutoriales de '.$category->name.' explicados en español, paso a paso y con herramientas probadas.'" />
    @else
        <x-seo title="Guías de IA y herramientas digitales" description="Tutoriales, comparativas y guías de IA y herramientas digitales, explicadas en español y con ejemplos reales." />
    @endif
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header :title="$category ? 'Guías de '.$category->name : 'Guías'" subtitle="Tutoriales y comparativas para sacar partido a las mejores apps."
            :back="$category ? ['url' => route('posts.index'), 'label' => 'Guías'] : null">
            @if($categories->isNotEmpty())
                <nav aria-label="Temas" class="nest-scroller mt-3">
                    <x-nest.chip :href="route('posts.index')" :active="! $category">Todas</x-nest.chip>
                    @foreach($categories as $item)
                        <x-nest.chip :href="route('posts.category', ['slug' => $item->slug])" :active="(bool) $category?->is($item)">{{ $item->name }}</x-nest.chip>
                    @endforeach
                </nav>
            @endif
        </x-nest.page-header>

        @if($posts->isEmpty())
            <x-nest.empty-state title="Todavía no hay guías aquí" icon="book-open">Mientras tanto, <a class="font-medium text-accent" href="{{ route('deals.index') }}">mira las ofertas</a>.</x-nest.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($posts as $post)
                    @include('public.partials.post-item', ['post' => $post])
                @endforeach
            </div>
            <x-nest.pagination :paginator="$posts" />
        @endif
    </div>
@endsection
