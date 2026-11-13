@extends('nest.layout')

@section('seo')
    <x-seo :content="$post" />
@endsection

@push('structured-data')
    @php($publisher = \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name'))
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->seo_description ?: $post->excerpt,
        'datePublished' => $post->published_at?->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString(),
        'mainEntityOfPage' => route('posts.show', ['slug' => $post->slug]),
        'author' => ['@type' => 'Organization', 'name' => $publisher],
        'publisher' => ['@type' => 'Organization', 'name' => $publisher],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <x-nest.breadcrumbs :items="[['Guías', route('posts.index')], [$post->title, route('posts.show', ['slug' => $post->slug])]]" />
    <div class="mx-auto flex max-w-2xl flex-col gap-8">
        <article class="flex flex-col gap-6">
            <x-nest.page-header :title="$post->title" :subtitle="$post->excerpt"
                :back="$post->categories->isNotEmpty() ? ['url' => route('posts.category', ['slug' => $post->categories->first()->slug]), 'label' => $post->categories->first()->name] : ['url' => route('posts.index'), 'label' => 'Guías']">
                <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted">
                    <span>Por {{ \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name') }}</span>
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j \d\e F \d\e Y') }}</time>
                    <span>{{ $post->readingMinutes() }} min de lectura</span>
                </p>
            </x-nest.page-header>

            @if($post->image)
                <figure class="overflow-hidden rounded-sheet border border-line [&_img]:h-auto [&_img]:w-full">
                    <x-content-image :media="$post->image" :alt="$post->imageAlt() ?? ''" :priority="true" />
                </figure>
            @endif

            <div class="nest-prose">{!! \App\Services\ContentHtml::sanitize($post->content) !!}</div>

            @if($post->categories->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($post->categories as $postCategory)
                        <x-nest.chip :href="route('posts.category', ['slug' => $postCategory->slug])">{{ $postCategory->name }}</x-nest.chip>
                    @endforeach
                </div>
            @endif
        </article>

        @if($related->isNotEmpty())
            <x-nest.section title="Sigue leyendo" :href="route('posts.index')" action="Todas las guías">
                <div class="flex flex-col gap-3">
                    @foreach($related as $relatedPost)
                        @include('public.partials.post-item', ['post' => $relatedPost])
                    @endforeach
                </div>
            </x-nest.section>
        @endif
    </div>
@endsection
