<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GuidesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listing_shows_published_guides_and_filters_by_category(): void
    {
        $ai = PostCategory::factory()->create(['name' => 'IA', 'slug' => 'ia']);
        $aiPost = Post::factory()->published()->create(['title' => 'Guía de IA']);
        $aiPost->categories()->attach($ai);
        Post::factory()->published()->create(['title' => 'Guía General']);
        Post::factory()->create(['title' => 'Guía Borrador']);

        $this->get(route('posts.index'))->assertOk()
            ->assertSee('Guía de IA')->assertSee('Guía General')->assertDontSee('Guía Borrador');

        $this->get(route('posts.category', ['slug' => 'ia']))->assertOk()
            ->assertSee('Guías de IA')->assertSee('Guía de IA')->assertDontSee('Guía General');

        $this->get(route('posts.category', ['slug' => 'no-existe']))->assertNotFound();
        $this->get(route('posts.index', ['page' => 'x']))->assertSessionHasErrors('page');
    }

    public function test_article_page_shows_reading_time_related_guides_and_article_data(): void
    {
        $topic = PostCategory::factory()->create(['slug' => 'tutoriales']);
        $post = Post::factory()->published()->create([
            'title' => 'Cómo usar Ollama',
            'content' => '<p>'.str_repeat('palabra ', 450).'</p><script>alert(1)</script>',
        ]);
        $post->categories()->attach($topic);
        $related = Post::factory()->published()->create(['title' => 'Otra guía del tema']);
        $related->categories()->attach($topic);

        $this->assertSame(3, $post->readingMinutes());

        $this->get(route('posts.show', ['slug' => $post->slug]))->assertOk()
            ->assertSee('Cómo usar Ollama')
            ->assertSee('3 min de lectura')
            ->assertSee('Otra guía del tema')
            ->assertSee('"@type":"Article"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_navigation_links_to_guides(): void
    {
        $this->get('/')->assertOk()->assertSee(route('posts.index'), false);
    }
}
