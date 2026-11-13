<?php

namespace Tests\Feature;

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Models\Redirect;
use App\Models\SearchSetting;
use App\Models\Template;
use App\Models\ToolCategory;
use App\Models\User;
use App\Models\Workflow;
use App\PublicationStatus;
use App\TemplateFormat;
use App\TemplateType;
use App\UserRole;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowAndTemplateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_permissions_by_role(): void
    {
        $workflow = Workflow::factory()->create();
        $template = Template::factory()->create();

        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin/workflows')->assertOk();
        $this->get('/admin/templates')->assertOk();
        $this->assertTrue(Gate::allows('delete', $workflow));

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->assertTrue(Gate::allows('update', $workflow));
        $this->assertTrue(Gate::allows('create', Template::class));
        $this->assertFalse(Gate::allows('delete', $template));

        $this->actingAs(User::factory()->create(['role' => UserRole::User]));
        $this->get('/admin/workflows')->assertForbidden();
        $this->get('/admin/templates')->assertForbidden();
    }

    public function test_publishing_a_workflow_requires_test_date_and_result(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = ToolCategory::factory()->create();

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'title' => 'Video en 20 minutos',
                'slug' => 'video-en-20-minutos',
                'summary' => 'Resumen',
                'goal' => 'Un video listo',
                'tool_category_id' => $category->id,
                'professions' => [WorkflowProfession::Creator->value],
                'difficulty' => WorkflowDifficulty::Basic->value,
                'time_minutes' => 20,
                'status' => PublicationStatus::Published->value,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['tested_at' => 'required', 'result']);
    }

    public function test_json_templates_must_contain_valid_json(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(CreateTemplate::class)
            ->fillForm([
                'title' => 'Automatización',
                'slug' => 'automatizacion',
                'summary' => 'Resumen',
                'type' => TemplateType::Automation->value,
                'format' => TemplateFormat::Json->value,
                'content' => '{no es json',
                'status' => PublicationStatus::Draft->value,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['content']);
    }

    public function test_rich_text_is_sanitized_and_slug_changes_create_no_public_redirects(): void
    {
        $workflow = Workflow::factory()->published()->create(['slug' => 'viejo', 'intro' => '<p>Hola</p><script>x</script>']);
        $this->assertStringNotContainsString('<script', (string) $workflow->intro);

        $workflow->update(['slug' => 'nuevo']);
        Template::factory()->published()->create(['slug' => 'antigua'])->update(['slug' => 'reciente']);

        $this->assertDatabaseCount(Redirect::class, 0);
    }

    public function test_workflows_and_templates_are_no_longer_public(): void
    {
        $workflow = Workflow::factory()->published()->create();
        $template = Template::factory()->published()->create();

        foreach (['/flujos', '/flujos/'.$workflow->slug, '/plantillas', '/plantillas/'.$template->slug, '/plantillas/'.$template->slug.'/descargar'] as $path) {
            $this->get($path)->assertRedirect('/explorar')->assertStatus(301);
        }
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true, 'sitemap_enabled' => true]);
        app()->instance('env', 'production');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/flujos', false)->assertDontSee('/plantillas', false);
    }
}
