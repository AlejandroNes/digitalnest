<?php

namespace Tests\Feature;

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Models\Redirect;
use App\Models\SearchSetting;
use App\Models\Template;
use App\Models\Tool;
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

    public function test_public_workflow_pages_show_steps_tools_and_filters(): void
    {
        $tool = Tool::factory()->published()->create(['name' => 'CapCut Ejemplo']);
        $draftTool = Tool::factory()->create(['name' => 'Herramienta Borrador']);
        $template = Template::factory()->published()->create(['title' => 'Guion Ejemplo']);
        $workflow = Workflow::factory()->published()->create([
            'title' => 'Flujo Publicado',
            'professions' => [WorkflowProfession::Creator->value],
        ]);
        $workflow->steps()->create(['position' => 0, 'title' => 'Paso uno', 'tool_id' => $tool->id, 'template_id' => $template->id]);
        $workflow->steps()->create(['position' => 1, 'title' => 'Paso dos', 'tool_id' => $draftTool->id]);
        Workflow::factory()->published()->create(['title' => 'Flujo Docentes', 'professions' => [WorkflowProfession::Teacher->value]]);
        Workflow::factory()->create(['title' => 'Flujo Borrador']);

        $this->get(route('workflows.index'))->assertOk()
            ->assertSee('Flujo Publicado')->assertSee('Flujo Docentes')->assertDontSee('Flujo Borrador');
        $this->get(route('workflows.index', ['para' => 'creator']))->assertOk()
            ->assertSee('Flujo Publicado')->assertDontSee('Flujo Docentes');
        $this->get(route('workflows.index', ['para' => 'astronauta']))->assertSessionHasErrors('para');

        $this->get(route('workflows.show', ['slug' => $workflow->slug]))->assertOk()
            ->assertSeeInOrder(['Paso uno', 'Paso dos'])
            ->assertSee('CapCut Ejemplo')->assertSee('Guion Ejemplo')
            ->assertDontSee('Herramienta Borrador')
            ->assertSee('"@type":"HowTo"', false);

        $this->get(route('tools.show', ['slug' => $tool->slug]))->assertOk()->assertSee('Flujos con CapCut Ejemplo');
    }

    public function test_draft_workflows_and_templates_return_404(): void
    {
        $workflow = Workflow::factory()->create();
        $template = Template::factory()->create();

        $this->get(route('workflows.show', ['slug' => $workflow->slug]))->assertNotFound();
        $this->get(route('templates.show', ['slug' => $template->slug]))->assertNotFound();
        $this->get(route('templates.download', ['slug' => $template->slug]))->assertNotFound();
    }

    public function test_template_page_escapes_content_and_download_uses_format(): void
    {
        $template = Template::factory()->published()->create([
            'title' => 'Plantilla Segura',
            'slug' => 'plantilla-segura',
            'content' => '<script>alert(1)</script> Escribe sobre [tema]',
            'format' => TemplateFormat::Markdown,
            'type' => TemplateType::Prompt,
        ]);

        $this->get(route('templates.show', ['slug' => 'plantilla-segura']))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('data-copy="contenido-plantilla"', false);

        $this->get(route('templates.download', ['slug' => 'plantilla-segura']))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="plantilla-segura.md"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<script>alert(1)</script> Escribe sobre [tema]', false);

        $this->assertStringStartsWith('text/markdown', $this->get(route('templates.download', ['slug' => 'plantilla-segura']))->headers->get('Content-Type'));
        $this->assertSame('plantilla-segura.md', $template->downloadFilename());
    }

    public function test_template_listing_filters_by_type_and_tool(): void
    {
        $tool = Tool::factory()->published()->create(['slug' => 'claude']);
        $prompt = Template::factory()->published()->create(['title' => 'Prompt Uno', 'type' => TemplateType::Prompt]);
        $prompt->tools()->attach($tool);
        Template::factory()->published()->create(['title' => 'Skill Dos', 'type' => TemplateType::Skill]);

        $this->get(route('templates.index', ['tipo' => 'skill']))->assertOk()->assertSee('Skill Dos')->assertDontSee('Prompt Uno');
        $this->get(route('templates.index', ['herramienta' => 'claude']))->assertOk()->assertSee('Prompt Uno')->assertDontSee('Skill Dos');
    }

    public function test_rich_text_is_sanitized_and_slug_changes_redirect(): void
    {
        $workflow = Workflow::factory()->published()->create(['slug' => 'viejo', 'intro' => '<p>Hola</p><script>x</script>']);
        $this->assertStringNotContainsString('<script', (string) $workflow->intro);

        $workflow->update(['slug' => 'nuevo']);
        $this->assertDatabaseHas(Redirect::class, ['source' => '/flujos/viejo', 'destination' => '/flujos/nuevo', 'status_code' => 301]);

        $template = Template::factory()->published()->create(['slug' => 'antigua']);
        $template->update(['slug' => 'reciente']);
        $this->assertDatabaseHas(Redirect::class, ['source' => '/plantillas/antigua', 'destination' => '/plantillas/reciente']);
    }

    public function test_sitemap_includes_published_workflows_and_templates(): void
    {
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        app()->instance('env', 'production');
        config(['app.url' => 'https://digitalnest.example.com']);

        Workflow::factory()->published()->create(['slug' => 'flujo-visible']);
        Workflow::factory()->create(['slug' => 'flujo-oculto']);
        Template::factory()->published()->create(['slug' => 'plantilla-visible']);
        Template::factory()->published()->create(['slug' => 'plantilla-noindex', 'seo_noindex' => true]);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('https://digitalnest.example.com/flujos</loc>', false)
            ->assertSee('https://digitalnest.example.com/flujos/flujo-visible</loc>', false)
            ->assertSee('https://digitalnest.example.com/plantillas/plantilla-visible</loc>', false)
            ->assertDontSee('flujo-oculto', false)
            ->assertDontSee('plantilla-noindex', false);
    }
}
