<a href="{{ route('posts.show', ['slug' => $post->slug]) }}" class="nest-card nest-pressable flex gap-4 p-3 hover:border-accent/40">
    @if($post->image)
        <span class="block aspect-[4/3] w-28 shrink-0 overflow-hidden rounded-xl bg-raised [&_img]:size-full [&_img]:object-cover" aria-hidden="true">@include('public.partials.post-media', ['post' => $post])</span>
    @endif
    <span class="flex min-w-0 flex-col gap-1 py-1">
        <span class="flex flex-wrap items-center gap-2 text-xs text-muted">
            @foreach($post->categories->take(2) as $postCategory)
                <span class="rounded-full bg-accent-soft px-2 py-0.5 font-medium text-accent">{{ $postCategory->name }}</span>
            @endforeach
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j M Y') }}</time>
            <span>{{ $post->readingMinutes() }} min</span>
        </span>
        <span class="line-clamp-2 leading-snug font-semibold">{{ $post->title }}</span>
        @if($post->excerpt)
            <span class="line-clamp-2 text-sm text-muted">{{ $post->excerpt }}</span>
        @endif
    </span>
</a>
