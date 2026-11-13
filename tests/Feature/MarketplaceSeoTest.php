<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Ranking;
use App\Models\SearchSetting;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MarketplaceSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sitemap_lists_indexable_marketplace_pages_only(): void
    {
        config(['app.url' => 'https://corporate.example.com']);
        SearchSetting::factory()->create(['indexable' => true, 'sitemap_enabled' => true]);
        app()->instance('env', 'production');
        $sector = ToolCategory::factory()->create(['slug' => 'audio']);
        ToolCategory::factory()->create(['slug' => 'oculto', 'seo_noindex' => true]);
        $tool = Tool::factory()->published()->create(['slug' => 'vozapp', 'tool_category_id' => $sector->id]);
        Deal::factory()->published()->create(['slug' => 'oferta-viva', 'tool_id' => $tool->id]);
        Deal::factory()->published()->expired()->create(['slug' => 'oferta-pasada']);
        Ranking::factory()->published()->create(['slug' => 'top-voz']);
        Ranking::factory()->create(['slug' => 'top-borrador']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(['/explorar', '/ofertas', '/top', '/sector/audio', '/app/vozapp', '/oferta/oferta-viva', '/top/top-voz'], false)
            ->assertDontSee(['/sector/oculto', '/oferta/oferta-pasada', '/top/top-borrador'], false);
    }

    public function test_deal_page_has_offer_and_breadcrumb_structured_data(): void
    {
        $deal = Deal::factory()->published()->create(['deal_price' => 9.99, 'currency' => 'EUR']);

        $this->get('/oferta/'.$deal->slug)
            ->assertOk()
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"priceCurrency":"EUR"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_nest_cookie_banner_offers_equal_choices(): void
    {
        SearchSetting::factory()->create(['cookies_enabled' => true]);

        $this->get('/ofertas')
            ->assertOk()
            ->assertSee('data-cookie-banner', false)
            ->assertSeeInOrder(['Rechazar opcionales', 'Aceptar opcionales'])
            ->assertSee('Guardar preferencias');
    }
}
