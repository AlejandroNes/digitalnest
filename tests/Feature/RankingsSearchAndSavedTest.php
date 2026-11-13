<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RankingsSearchAndSavedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rankings_index_and_detail_list_published_tools_in_order(): void
    {
        $ranking = Ranking::factory()->published()->create(['title' => 'Mejores IA de voz']);
        $first = Tool::factory()->published()->create(['name' => 'Primera']);
        $second = Tool::factory()->published()->create(['name' => 'Segunda']);
        $hidden = Tool::factory()->create(['name' => 'Oculta']);
        $ranking->tools()->attach([
            $second->id => ['position' => 2, 'verdict' => null, 'best_for' => null],
            $first->id => ['position' => 1, 'verdict' => 'La más natural', 'best_for' => 'Podcasts'],
            $hidden->id => ['position' => 3, 'verdict' => null, 'best_for' => null],
        ]);
        Deal::factory()->published()->create(['tool_id' => $first->id, 'title' => 'Voz 30% off']);
        $draft = Ranking::factory()->create(['title' => 'Ranking borrador']);

        $this->get('/top')->assertOk()->assertSee('Mejores IA de voz')->assertDontSee('Ranking borrador');
        $this->get('/top/'.$ranking->slug)
            ->assertOk()
            ->assertSeeInOrder(['Primera', 'La más natural', 'Voz 30% off', 'Segunda'])
            ->assertSee('Mejor para: Podcasts')
            ->assertDontSee('Oculta')
            ->assertSee('"@type":"ItemList"', false);
        $this->get('/top/'.$draft->slug)->assertNotFound();
    }

    public function test_search_finds_deals_sectors_and_rankings(): void
    {
        $tool = Tool::factory()->published()->create(['name' => 'Hostazo']);
        Deal::factory()->published()->create(['tool_id' => $tool->id, 'title' => 'Plan anual rebajado']);
        Deal::factory()->published()->expired()->create(['title' => 'Hosting caducado']);
        ToolCategory::factory()->create(['name' => 'Hosting web']);
        Ranking::factory()->published()->create(['title' => 'Mejor hosting barato']);

        $this->get(route('search', ['q' => 'host']))
            ->assertOk()
            ->assertSee(['Plan anual rebajado', 'Hosting web', 'Mejor hosting barato', 'Hostazo'])
            ->assertDontSee('Hosting caducado');
    }

    public function test_saved_page_and_cards_endpoint(): void
    {
        $visible = Deal::factory()->published()->create(['title' => 'Guardada visible']);
        $draft = Deal::factory()->create(['title' => 'Guardada borrador']);

        $this->get('/guardados')->assertOk()->assertSee('data-saved-list', false)->assertSee('noindex', false);
        $this->get(route('saved.cards', ['slugs' => [$visible->slug, $draft->slug]]))
            ->assertOk()
            ->assertSee('Guardada visible')
            ->assertSee('data-save-deal="'.$visible->slug.'"', false)
            ->assertDontSee('Guardada borrador');
        $this->get(route('saved.cards'))->assertOk()->assertSee('ya no están disponibles');
        $this->getJson(route('saved.cards', ['slugs' => ['<script>']]))->assertUnprocessable();
        $this->getJson(route('saved.cards', ['slugs' => array_fill(0, 51, 'a')]))->assertUnprocessable();
    }
}
