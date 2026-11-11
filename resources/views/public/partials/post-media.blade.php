@if($post->image)
    <x-content-image :media="$post->image" :alt="$post->imageAlt() ?? ''" :priority="$priority ?? false" />
@endif
