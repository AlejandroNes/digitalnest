<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicAppScreensTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_shows_active_deals_sectors_and_ranking(): void
    {
        $sector = ToolCategory::factory()->create(['name' => 'Vídeo IA']);
        $deal = Deal::factory()->published()->coupon('HOLA10')->create(['title' => 'Cupón activo']);
        Deal::factory()->published()->expired()->create(['title' => 'Cupón caducado']);
        Deal::factory()->create(['title' => 'Cupón borrador']);
        $ranking = Ranking::factory()->published()->create(['title' => 'Top IA de vídeo']);
        $ranking->tools()->attach($deal->tool_id, ['position' => 1, 'verdict' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Cupón activo')
            ->assertSee('data-copy-code="HOLA10"', false)
            ->assertSee($sector->name)
            ->assertSee('Top IA de vídeo')
            ->assertDontSee('Cupón caducado')
            ->assertDontSee('Cupón borrador');
    }

    public function test_home_renders_without_content(): void
    {
        $this->get('/')->assertOk()->assertSee('Pronto habrá ofertas');
    }

    public function test_deals_feed_filters_and_rejects_unknown_filter(): void
    {
        Deal::factory()->published()->coupon()->create(['title' => 'Es un cupón']);
        Deal::factory()->published()->create(['title' => 'Es un descuento']);
        Deal::factory()->published()->create(['title' => 'Caduca mañana', 'expires_at' => now()->addDay()]);

        $this->get('/ofertas')->assertOk()->assertSee(['Es un cupón', 'Es un descuento', 'Caduca mañana']);
        $this->get('/ofertas?filtro=cupones')->assertOk()->assertSee('Es un cupón')->assertDontSee('Es un descuento')
            ->assertSee('noindex', false);
        $this->get('/ofertas?filtro=caducan-pronto')->assertOk()->assertSee('Caduca mañana')->assertDontSee('Es un cupón');
        $this->get('/ofertas?filtro=<script>')->assertRedirect();
    }

    public function test_deal_detail_handles_expired_and_hidden_deals(): void
    {
        $active = Deal::factory()->published()->create(['title' => 'Oferta viva']);
        $expired = Deal::factory()->published()->expired()->create(['title' => 'Oferta pasada']);
        $draft = Deal::factory()->create();
        $hiddenTool = Deal::factory()->published()->create(['tool_id' => Tool::factory()->create()->id]);

        $this->get('/oferta/'.$active->slug)->assertOk()->assertSee('Oferta viva')->assertSee('/go/'.$active->slug, false);
        $this->get('/oferta/'.$expired->slug)->assertOk()->assertSee('Esta oferta caducó')
            ->assertSee('noindex', false)->assertDontSee('/go/'.$expired->slug, false);
        $this->get('/oferta/'.$draft->slug)->assertNotFound();
        $this->get('/oferta/'.$hiddenTool->slug)->assertNotFound();
    }

    public function test_go_redirects_counts_clicks_and_blocks_hidden_deals(): void
    {
        $deal = Deal::factory()->published()->create(['url' => 'https://partner.example/deal']);
        $draft = Deal::factory()->create(['url' => 'https://partner.example/draft']);

        $this->get('/go/'.$deal->slug)
            ->assertRedirect('https://partner.example/deal')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/go/'.$deal->slug);

        $this->assertSame(2, $deal->fresh()->clicks);
        $this->get('/go/'.$draft->slug)->assertNotFound();
        $this->assertSame(0, $draft->fresh()->clicks);
    }

    public function test_explore_and_sector_include_subcategory_content(): void
    {
        $parent = ToolCategory::factory()->create(['name' => 'Audio']);
        $child = ToolCategory::factory()->create(['name' => 'Voz IA', 'parent_id' => $parent->id]);
        $tool = Tool::factory()->published()->create(['name' => 'VozMágica', 'tool_category_id' => $child->id]);
        Deal::factory()->published()->create(['tool_id' => $tool->id, 'title' => 'Voz al 30%']);
        ToolCategory::factory()->inactive()->create(['name' => 'Sector oculto']);

        $this->get('/explorar')->assertOk()->assertSee(['Audio', 'Voz IA'])->assertDontSee('Sector oculto');
        $this->get('/sector/'.$parent->slug)->assertOk()->assertSee(['VozMágica', 'Voz al 30%']);
        $this->get('/sector/'.$child->slug)->assertOk()->assertSee('VozMágica')->assertSee('Audio');
        $this->get('/sector/no-existe')->assertNotFound();
    }

    public function test_app_profile_shows_deals_and_hides_unpublished(): void
    {
        $tool = Tool::factory()->published()->create(['name' => 'SuperApp', 'affiliate_url' => 'https://aff.example/x']);
        Deal::factory()->published()->create(['tool_id' => $tool->id, 'title' => 'SuperApp -40%']);
        $draft = Tool::factory()->create();

        $this->get('/app/'.$tool->slug)
            ->assertOk()
            ->assertSee(['SuperApp', 'SuperApp -40%'])
            ->assertSee('rel="sponsored nofollow noopener"', false)
            ->assertSee('"@type":"SoftwareApplication"', false);
        $this->get('/app/'.$draft->slug)->assertNotFound();
    }
}
