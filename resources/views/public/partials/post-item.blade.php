<li class="dn-post {{ $post->image ? 'dn-post--media' : '' }}">
    @if($post->image)
        <div class="dn-post-media" aria-hidden="true">@include('public.partials.post-media', ['post' => $post])</div>
    @endif
    <div>
        <p class="dn-byline">
            @foreach($post->categories->take(2) as $postCategory)
                <span class="dn-pill dn-pill--sky">{{ $postCategory->name }}</span>
            @endforeach
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j M Y') }}</time>
            <span>{{ $post->readingMinutes() }} min</span>
        </p>
        <a class="dn-item-title" style="display:block; margin-top:.4rem" href="{{ route('posts.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a>
        @if($post->excerpt)
            <p class="dn-tool-tagline">{{ $post->excerpt }}</p>
        @endif
    </div>
</li>
