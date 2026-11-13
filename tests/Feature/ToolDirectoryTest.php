<?php

namespace Tests\Feature;

use App\Filament\Resources\ToolCategories\Pages\ListToolCategories;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Models\Redirect;
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

    public function test_changing_slug_creates_redirect_to_new_app_profile(): void
    {
        $tool = Tool::factory()->published()->create(['slug' => 'antiguo']);
        $tool->update(['slug' => 'nuevo']);

        $this->assertDatabaseHas(Redirect::class, ['source' => '/app/antiguo', 'destination' => '/app/nuevo', 'status_code' => 301]);
    }

    public function test_old_directory_urls_redirect_permanently_to_the_deals_app(): void
    {
        $this->get('/herramientas')->assertRedirect('/explorar')->assertStatus(301);
        $this->get('/herramientas/categoria/video')->assertRedirect(route('sectors.show', ['slug' => 'video']))->assertStatus(301);
        $this->get('/herramientas/canva')->assertRedirect(route('apps.show', ['slug' => 'canva']))->assertStatus(301);
        $this->get('/herramientas/canva/alternativas')->assertRedirect(route('apps.show', ['slug' => 'canva']))->assertStatus(301);
    }

    public function test_unsafe_outbound_urls_are_not_rendered_on_app_profile(): void
    {
        $tool = Tool::factory()->published()->create(['website_url' => 'javascript:alert(1)', 'affiliate_url' => 'data:text/html,x']);

        $this->get(route('apps.show', ['slug' => $tool->slug]))->assertOk()
            ->assertDontSee('javascript:alert', false)->assertDontSee('data:text/html', false);
    }
}
