<?php

namespace Tests\Feature;

use App\Filament\Resources\ToolCategories\Pages\ListToolCategories;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Models\Redirect;
use App\Models\SearchSetting;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ToolDirectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_permissions_by_role(): void
    {
        $tool = Tool::factory()->create();
        $category = $tool->category;
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $client = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs(User::factory()->administrator()->create());
        $this->assertTrue(Gate::allows('delete', $tool));
        $this->get('/admin/tools')->assertOk();
        $this->get('/admin/tool-categories')->assertOk();

        $this->actingAs($editor);
        $this->assertTrue(Gate::allows('create', Tool::class));
        $this->assertTrue(Gate::allows('update', $tool));
        $this->assertFalse(Gate::allows('delete', $tool));
        $this->assertFalse(Gate::allows('delete', $category));
        $this->get('/admin/tools/'.$tool->slug.'/edit')->assertOk();

        $this->actingAs($client);
        $this->assertFalse(Gate::allows('viewAny', Tool::class));
        $this->get('/admin/tools')->assertForbidden();

        auth()->logout();
        $this->get('/admin/tools')->assertRedirect('/admin/login');
    }

    public function test_category_with_tools_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $used = Tool::factory()->create()->category;
        $empty = ToolCategory::factory()->create();

        $this->assertFalse(Gate::allows('delete', $used));
        $this->assertTrue(Gate::allows('delete', $empty));
        Livewire::test(ListToolCategories::class)->assertCanSeeTableRecords([$used, $empty]);
    }

    public function test_administrator_creates_draft_and_publishing_requires_test_data(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = ToolCategory::factory()->create();
        $base = [
            'name' => 'Ideogram',
            'slug' => 'ideogram',
            'tagline' => 'Genera imágenes con texto legible.',
            'website_url' => 'https://ideogram.ai',
            'pricing_model' => ToolPricingModel::Freemium->value,
            'spanish_support' => ToolSpanishSupport::Partial->value,
            'tool_category_id' => $category->id,
            'sort_order' => 0,
        ];

        Livewire::test(CreateTool::class)
            ->fillForm([...$base, 'status' => ToolStatus::Published->value])
            ->call('create')
            ->assertHasFormErrors(['tested_at' => 'required', 'review', 'best_for' => 'required']);

        Livewire::test(CreateTool::class)
            ->fillForm([...$base, 'status' => ToolStatus::Draft->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $tool = Tool::query()->where('slug', 'ideogram')->firstOrFail();
        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);

        $tool->update(['pros' => ['Texto en imágenes', ' ', ['text' => 'Rápida']]]);
        $this->assertSame(['Texto en imágenes', 'Rápida'], $tool->fresh()->prosList());
    }

    public function test_alternatives_can_be_edited_and_exclude_the_tool_itself(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $tool = Tool::factory()->create();
        $alternative = Tool::factory()->create();

        Livewire::test(EditTool::class, ['record' => $tool->slug])
            ->fillForm(['alternatives' => [$alternative->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($tool->alternatives()->whereKey($alternative->id)->exists());
        Livewire::test(ListTools::class)->assertCanSeeTableRecords([$tool, $alternative]);
    }

    public function test_publishing_sets_publication_date_and_review_html_is_sanitized(): void
    {
        $tool = Tool::factory()->create(['review' => '<p>Bien</p><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script', (string) $tool->review);

        $tool->update(['status' => ToolStatus::Published]);
        $this->assertNotNull($tool->fresh()->published_at);
    }

    public function test_public_listing_shows_only_published_tools_and_filters(): void
    {
        $free = Tool::factory()->published()->create(['name' => 'Herramienta Libre', 'pricing_model' => ToolPricingModel::Free]);
        $paid = Tool::factory()->published()->create(['name' => 'Herramienta Pago', 'pricing_model' => ToolPricingModel::Paid, 'has_free_plan' => false]);
        Tool::factory()->create(['name' => 'Herramienta Borrador']);
        Tool::factory()->published()->create(['name' => 'Herramienta Futura', 'published_at' => now()->addDay()]);

        $this->get(route('tools.index'))->assertOk()
            ->assertSee('Herramienta Libre')->assertSee('Herramienta Pago')
            ->assertDontSee('Herramienta Borrador')->assertDontSee('Herramienta Futura');

        $this->get(route('tools.index', ['precio' => 'free']))->assertOk()
            ->assertSee('Herramienta Libre')->assertDontSee('Herramienta Pago');

        $this->get(route('tools.index', ['gratis' => 1]))->assertOk()->assertDontSee('Herramienta Pago');
        $this->get(route('tools.index', ['precio' => 'invalido']))->assertSessionHasErrors('precio');

        $this->get(route('tools.category', ['slug' => $paid->category->slug]))->assertOk()
            ->assertSee('Herramienta Pago')->assertDontSee('Herramienta Libre');
        $this->assertNotNull($free);
    }

    public function test_category_page_includes_secondary_tools_and_inactive_categories_404(): void
    {
        $category = ToolCategory::factory()->create();
        $tool = Tool::factory()->published()->create(['name' => 'Herramienta Secundaria']);
        $tool->secondaryCategories()->attach($category);

        $this->get(route('tools.category', ['slug' => $category->slug]))->assertOk()->assertSee('Herramienta Secundaria');

        $category->update(['is_active' => false]);
        $this->get(route('tools.category', ['slug' => $category->slug]))->assertNotFound();
    }

    public function test_tool_page_renders_details_structured_data_and_sponsored_link(): void
    {
        $tool = Tool::factory()->published()->create([
            'name' => 'Cursor',
            'slug' => 'cursor',
            'affiliate_url' => 'https://example.com/ref/cursor',
            'rating' => 9,
            'deal_notes' => '20 % de descuento',
            'deal_expires_at' => now()->addWeek(),
        ]);

        $this->get(route('tools.show', ['slug' => 'cursor']))->assertOk()
            ->assertSee('Cursor')
            ->assertSee('rel="sponsored noopener"', false)
            ->assertSee('https://example.com/ref/cursor', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('"ratingValue":9', false)
            ->assertSee('20 % de descuento');

        $tool->update(['deal_expires_at' => now()->subDay()]);
        $this->get(route('tools.show', ['slug' => 'cursor']))->assertDontSee('20 % de descuento');
    }

    public function test_unsafe_outbound_urls_are_not_rendered(): void
    {
        $tool = Tool::factory()->published()->create(['website_url' => 'javascript:alert(1)', 'affiliate_url' => null]);

        $this->assertNull($tool->outboundUrl());
        $this->get(route('tools.show', ['slug' => $tool->slug]))->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_draft_tools_return_404(): void
    {
        $tool = Tool::factory()->create();

        $this->get(route('tools.show', ['slug' => $tool->slug]))->assertNotFound();
        $this->get(route('tools.alternatives', ['slug' => $tool->slug]))->assertNotFound();
    }

    public function test_alternatives_page_lists_only_published_alternatives(): void
    {
        $tool = Tool::factory()->published()->create(['name' => 'Canva']);
        $published = Tool::factory()->published()->create(['name' => 'Alternativa Publicada']);
        $draft = Tool::factory()->create(['name' => 'Alternativa Borrador']);

        $this->get(route('tools.alternatives', ['slug' => $tool->slug]))->assertNotFound();

        $tool->alternatives()->attach([$published->id => ['sort_order' => 1], $draft->id => ['sort_order' => 2]]);

        $this->get(route('tools.alternatives', ['slug' => $tool->slug]))->assertOk()
            ->assertSee('Alternativas a Canva')
            ->assertSee('Alternativa Publicada')
            ->assertDontSee('Alternativa Borrador');
    }

    public function test_changing_slug_creates_redirects(): void
    {
        $tool = Tool::factory()->published()->create(['slug' => 'antiguo']);
        $tool->update(['slug' => 'nuevo']);

        $this->assertDatabaseHas(Redirect::class, ['source' => '/herramientas/antiguo', 'destination' => '/herramientas/nuevo', 'status_code' => 301]);
        $this->assertDatabaseHas(Redirect::class, ['source' => '/herramientas/antiguo/alternativas', 'destination' => '/herramientas/nuevo/alternativas']);
    }

    public function test_sitemap_includes_published_indexable_tools(): void
    {
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        app()->instance('env', 'production');
        config(['app.url' => 'https://digitalnest.example.com']);

        $tool = Tool::factory()->published()->create(['slug' => 'publicada']);
        $tool->alternatives()->attach(Tool::factory()->published()->create(['slug' => 'alternativa'])->id);
        Tool::factory()->published()->create(['slug' => 'oculta', 'seo_noindex' => true]);
        Tool::factory()->create(['slug' => 'borrador']);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>https://digitalnest.example.com/herramientas</loc>', false)
            ->assertSee('https://digitalnest.example.com/herramientas/publicada</loc>', false)
            ->assertSee('https://digitalnest.example.com/herramientas/publicada/alternativas</loc>', false)
            ->assertSee('https://digitalnest.example.com/herramientas/categoria/'.$tool->category->slug.'</loc>', false)
            ->assertDontSee('/herramientas/oculta', false)
            ->assertDontSee('/herramientas/borrador', false);
    }
}
