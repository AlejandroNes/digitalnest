@extends('public.layout')

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
    <div class="dn-wrap">
        <article class="dn-article">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                <a href="{{ route('posts.index') }}">Guías</a>
                @if($post->categories->isNotEmpty())
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('posts.category', ['slug' => $post->categories->first()->slug]) }}">{{ $post->categories->first()->name }}</a>
                @endif
            </nav>

            <header class="dn-article-head">
                <h1 class="dn-display dn-title" style="margin-top:1.25rem">{{ $post->title }}</h1>
                @if($post->excerpt)
                    <p class="dn-lede" style="max-width:none">{{ $post->excerpt }}</p>
                @endif
                <p class="dn-byline" style="margin-top:1.25rem">
                    <span>Por {{ \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name') }}</span>
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j \d\e F \d\e Y') }}</time>
                    <span>{{ $post->readingMinutes() }} min de lectura</span>
                </p>
            </header>

            @if($post->image)
                <figure class="dn-article-cover">
                    <x-content-image :media="$post->image" :alt="$post->imageAlt() ?? ''" :priority="true" />
                </figure>
            @endif

            <div class="dn-prose">{!! \App\Services\ContentHtml::sanitize($post->content) !!}</div>

            @if($post->categories->isNotEmpty())
                <p class="dn-meta" style="margin-top:2.5rem">
                    @foreach($post->categories as $postCategory)
                        <a class="dn-pill dn-pill--sky" style="text-decoration:none" href="{{ route('posts.category', ['slug' => $postCategory->slug]) }}">{{ $postCategory->name }}</a>
                    @endforeach
                </p>
            @endif
        </article>

        @if($related->isNotEmpty())
            <section class="dn-section" aria-labelledby="relacionadas" style="padding-bottom:0">
                <div class="dn-section-head">
                    <h2 id="relacionadas" class="dn-h2">Sigue leyendo</h2>
                    <a class="dn-link" href="{{ route('posts.index') }}">Todas las guías @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
                </div>
                <div class="dn-panel"><ul class="dn-items">
                    @foreach($related as $relatedPost)
                        @include('public.partials.post-item', ['post' => $relatedPost])
                    @endforeach
                </ul></div>
            </section>
        @endif
    </div>
@endsection
