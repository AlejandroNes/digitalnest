<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Template;
use App\Models\Tool;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_finds_published_content_across_sections(): void
    {
        Tool::factory()->published()->create(['name' => 'Editor de Video Rápido', 'tagline' => 'Corta clips']);
        Tool::factory()->create(['name' => 'Video Borrador']);
        Workflow::factory()->published()->create(['title' => 'Crea un video para redes', 'summary' => 'Resumen']);
        Template::factory()->published()->create(['title' => 'Guion corto', 'summary' => 'Prompt para tu video vertical']);
        Post::factory()->published()->create(['title' => 'Cómo editar video con IA', 'excerpt' => 'Guía']);
        Post::factory()->published()->create(['title' => 'Otra cosa', 'excerpt' => 'Nada que ver']);

        $this->get(route('search', ['q' => 'VIDEO']))->assertOk()
            ->assertSee('Editor de Video Rápido')
            ->assertSee('Crea un video para redes')
            ->assertSee('Guion corto')
            ->assertSee('Cómo editar video con IA')
            ->assertDontSee('Video Borrador')
            ->assertDontSee('Otra cosa')
            ->assertSee('4 resultados')
            ->assertSee('noindex, follow', false);
    }

    public function test_every_word_must_match(): void
    {
        Tool::factory()->published()->create(['name' => 'Editor de video', 'tagline' => 'Para redes']);
        Tool::factory()->published()->create(['name' => 'Editor de texto', 'tagline' => 'Para blogs']);

        $this->get(route('search', ['q' => 'editor video']))->assertOk()
            ->assertSee('Editor de video')->assertDontSee('Editor de texto');
    }

    public function test_like_wildcards_are_treated_as_text(): void
    {
        Tool::factory()->published()->create(['name' => 'Herramienta Normal']);

        $this->get(route('search', ['q' => '%%']))->assertOk()->assertDontSee('Herramienta Normal')->assertSee('No encontramos nada');
        $this->get(route('search', ['q' => '__']))->assertOk()->assertDontSee('Herramienta Normal');
    }

    public function test_short_empty_and_too_long_queries(): void
    {
        $this->get(route('search'))->assertOk()->assertSee('Buscar');
        $this->get(route('search', ['q' => 'a']))->assertOk()->assertSee('Escribe al menos dos letras');
        $this->get(route('search', ['q' => str_repeat('a', 101)]))->assertSessionHasErrors('q');
    }

    public function test_search_term_is_escaped(): void
    {
        $this->get(route('search', ['q' => '<script>alert(1)</script>']))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_home_shows_the_search_form(): void
    {
        $this->get('/')->assertOk()->assertSee('role="search"', false)->assertSee(route('search'), false);
    }
}
