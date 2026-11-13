<?php

namespace Tests\Feature;

use App\DealType;
use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Redirect;
use App\Models\Tool;
use App\Models\ToolCategory;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DealsAndRankingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_scope_excludes_drafts_expired_future_and_unpublished_tools(): void
    {
        $active = Deal::factory()->published()->create();
        Deal::factory()->create();
        Deal::factory()->published()->expired()->create();
        Deal::factory()->published()->create(['starts_at' => now()->addDay()]);
        Deal::factory()->published()->create(['tool_id' => Tool::factory()->create()->id]);

        $this->assertSame([$active->id], Deal::query()->active()->pluck('id')->all());
    }

    public function test_discount_percent_is_derived_from_prices(): void
    {
        $deal = Deal::factory()->create(['original_price' => 80, 'deal_price' => 20]);

        $this->assertSame(75, $deal->discount_percent);
    }

    public function test_publishing_sets_published_at_and_slug_is_generated(): void
    {
        $deal = Deal::factory()->create(['slug' => null, 'title' => 'Canva Pro 50% off', 'status' => 'published', 'published_at' => null]);

        $this->assertSame('canva-pro-50-off', $deal->slug);
        $this->assertNotNull($deal->published_at);
    }

    public function test_outbound_url_rejects_unsafe_links_and_falls_back_to_tool(): void
    {
        $tool = Tool::factory()->published()->create(['website_url' => 'https://tool.example']);
        $deal = Deal::factory()->coupon('SAVE20')->create(['tool_id' => $tool->id, 'url' => 'javascript:alert(1)']);

        $this->assertSame('https://tool.example', $deal->outboundUrl());
        $this->assertTrue($deal->hasCode());
        $this->assertSame(DealType::Coupon, $deal->type);

        $deal->update(['url' => 'https://partner.example/offer']);
        $this->assertSame('https://partner.example/offer', $deal->outboundUrl());
    }

    public function test_expire_command_unfeatures_only_expired_deals(): void
    {
        $expired = Deal::factory()->published()->expired()->featured()->create();
        $current = Deal::factory()->published()->featured()->create();

        $this->artisan('app:expire-deals')->assertSuccessful();

        $this->assertFalse($expired->fresh()->is_featured);
        $this->assertTrue($current->fresh()->is_featured);
        $this->assertTrue($expired->fresh()->isExpired());
    }

    public function test_ranking_orders_tools_by_position_and_published_scope(): void
    {
        $ranking = Ranking::factory()->published()->create();
        $second = Tool::factory()->published()->create();
        $first = Tool::factory()->published()->create();
        $ranking->tools()->attach([$second->id => ['position' => 2, 'verdict' => null], $first->id => ['position' => 1, 'verdict' => 'La mejor']]);
        Ranking::factory()->create();

        $this->assertSame([$first->id, $second->id], $ranking->tools()->pluck('tools.id')->all());
        $this->assertSame('La mejor', $ranking->tools->first()->pivot->verdict);
        $this->assertSame([$ranking->id], Ranking::query()->published()->pluck('id')->all());
    }

    public function test_sectors_support_hierarchy(): void
    {
        $parent = ToolCategory::factory()->create(['icon' => 'sparkles', 'accent_color' => '#7C3AED']);
        $child = ToolCategory::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($child->parent->is($parent));
        $this->assertSame([$parent->id], ToolCategory::query()->roots()->pluck('id')->all());
    }

    public function test_deleting_tool_cascades_to_deals(): void
    {
        $deal = Deal::factory()->create();

        $deal->tool->delete();

        $this->assertModelMissing($deal);
    }

    public function test_sector_seeder_is_idempotent_and_keeps_edits(): void
    {
        ToolCategory::factory()->create(['slug' => 'video', 'name' => 'Vídeo editado', 'icon' => null]);

        $this->seed(SectorSeeder::class);
        $count = ToolCategory::query()->count();
        $this->seed(SectorSeeder::class);

        $this->assertSame($count, ToolCategory::query()->count());
        $video = ToolCategory::query()->where('slug', 'video')->firstOrFail();
        $this->assertSame('Vídeo editado', $video->name);
        $this->assertSame('film', $video->icon);
        $this->assertSame(12, ToolCategory::query()->roots()->count());
        $this->assertTrue(ToolCategory::query()->where('slug', 'vpn')->firstOrFail()->parent->is(
            ToolCategory::query()->where('slug', 'vpn-seguridad')->firstOrFail()
        ));
    }

    public function test_changing_deal_or_ranking_slug_redirects_old_url(): void
    {
        Deal::factory()->published()->create(['slug' => 'oferta-vieja'])->update(['slug' => 'oferta-nueva']);
        Ranking::factory()->published()->create(['slug' => 'top-viejo'])->update(['slug' => 'top-nuevo']);

        $this->assertDatabaseHas(Redirect::class, ['source' => '/oferta/oferta-vieja', 'destination' => '/oferta/oferta-nueva', 'status_code' => 301]);
        $this->assertDatabaseHas(Redirect::class, ['source' => '/top/top-viejo', 'destination' => '/top/top-nuevo']);
    }
}
