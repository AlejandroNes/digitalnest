<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Livewire\Portfolio;
use App\Models\MediaAsset;
use App\Models\Permission as PermissionModel;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\ProjectStatus;
use App\Services\MediaUploader;
use App\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function administrator(): User
    {
        Storage::fake('public');
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);

        return $user;
    }

    private function image(string $name = 'portfolio', int $width = 600): MediaAsset
    {
        return app(MediaUploader::class)->upload(auth()->user(), UploadedFile::fake()->image($name.'.png', $width, 400), $name, 'Trabajo realizado')['asset'];
    }

    public function test_form_requires_title_image_category_and_valid_status(): void
    {
        $this->administrator();
        Livewire::test(CreateProject::class)->fillForm(['title' => null, 'status' => null])
            ->call('create')->assertHasFormErrors(['title' => 'required', 'image_id' => 'required', 'project_category_id' => 'required', 'status' => 'required']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_only_the_requested_fields_can_be_written_through_the_form(): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        $page = Livewire::test(CreateProject::class);
        foreach (['slug', 'location', 'short_description', 'content', 'galleryImages', 'is_featured', 'sort_order', 'seo_title', 'seo_image_id'] as $field) {
            $page->assertFormFieldDoesNotExist($field);
        }
        $page->fillForm([
            'title' => 'Trabajo sencillo', 'image_id' => $media->id,
            'project_category_id' => ProjectCategory::factory()->create()->id,
            'location' => 'No permitido', 'is_featured' => true, 'sort_order' => 99, 'seo_title' => 'No permitido',
            'galleryImages' => [['media_id' => $media->id]],
        ])->call('create')->assertHasNoFormErrors();
        $project = Project::query()->firstOrFail();
        $this->assertNull($project->location);
        $this->assertNull($project->seo_title);
        $this->assertFalse($project->is_featured);
        $this->assertSame(0, $project->sort_order);
        $this->assertDatabaseCount('project_images', 0);
    }

    #[TestWith(['external_url', 'javascript:alert(1)', 'url'])]
    #[TestWith(['external_url', 'ftp://example.test/file', 'url'])]
    #[TestWith(['project_category_id', 999999, 'exists'])]
    #[TestWith(['status', 'archived', 'Illuminate\Validation\Rules\Enum'])]
    public function test_invalid_project_values_are_rejected(string $field, mixed $value, string $rule): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        Livewire::test(CreateProject::class)->fillForm([
            'title' => 'Trabajo', 'image_id' => $media->id,
            'project_category_id' => ProjectCategory::factory()->create()->id, $field => $value,
        ])->call('create')->assertHasFormErrors([$field]);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_saving_rechecks_the_selected_original_file(): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        $page = Livewire::test(CreateProject::class)->fillForm([
            'title' => 'Trabajo', 'image_id' => $media->id,
            'project_category_id' => ProjectCategory::factory()->create()->id,
        ]);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $page->call('create')->assertHasFormErrors(['image_id']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_replacing_and_deleting_projects_keeps_shared_library_files_and_other_references(): void
    {
        $this->administrator();
        $oldAsset = $this->image('previous');
        $newAsset = $this->image('new', 601);
        $old = $oldAsset->getFirstMedia('images');
        $new = $newAsset->getFirstMedia('images');
        $project = Project::factory()->withCategory()->create(['image_id' => $old->id]);
        $other = Project::factory()->create(['image_id' => $old->id]);
        $gallery = ProjectImage::factory()->for($project)->create(['media_id' => $old->id]);
        $files = Storage::disk('public')->allFiles();

        Livewire::test(EditProject::class, ['record' => $project->slug])->fillForm(['image_id' => $new->id])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($new->id, $project->fresh()->image_id);
        $this->assertModelExists($gallery);
        Livewire::test(EditProject::class, ['record' => $project->slug])->callAction(DeleteAction::class);
        $this->assertModelMissing($project);
        $this->assertModelMissing($gallery);
        $this->assertSame($old->id, $other->fresh()->image_id);
        $this->assertTrue($oldAsset->isReferenced());
        $this->assertFalse($newAsset->isReferenced());
        $this->assertDatabaseCount('media', 2);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_editing_unrelated_fields_preserves_legacy_descriptions_seo_and_gallery(): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        $project = Project::factory()->withCategory()->create([
            'image_id' => $media->id, 'short_description' => 'Resumen conservado.',
            'content' => '<p>Primera línea.</p><p>Segunda línea.</p>', 'location' => 'Madrid',
            'is_featured' => true, 'sort_order' => 7, 'seo_title' => 'SEO anterior',
        ]);
        $legacy = $project->fresh()->getAttributes();
        $gallery = ProjectImage::factory()->for($project)->create(['media_id' => $media->id, 'caption' => 'Foto antigua']);
        Livewire::test(EditProject::class, ['record' => $project->slug])
            ->assertSet('data.description', "Resumen conservado.\n\nPrimera línea.\nSegunda línea.")
            ->fillForm(['title' => 'Título actualizado'])->call('save')->assertHasNoFormErrors();
        foreach (['slug', 'short_description', 'content', 'location', 'is_featured', 'sort_order', 'seo_title'] as $field) {
            $this->assertSame($legacy[$field], $project->fresh()->getRawOriginal($field));
        }
        $this->assertModelExists($gallery);
    }

    public function test_plain_description_keeps_line_breaks_and_is_escaped_in_the_modal(): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        $description = "Un trabajo & sus resultados.\n<script>alert('test')</script>";
        Livewire::test(CreateProject::class)->fillForm([
            'title' => '<img src=x onerror=alert(1)>', 'description' => $description,
            'client' => '<script>cliente</script>', 'image_id' => $media->id,
            'project_category_id' => ProjectCategory::factory()->create()->id, 'status' => 'published',
        ])->call('create')->assertHasNoFormErrors();
        $project = Project::query()->firstOrFail();
        $this->assertSame($description, $project->portfolioDescription());
        $this->get('/proyectos?proyecto='.$project->slug)->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee("<script>alert('test')</script>", false)
            ->assertDontSee('<script>cliente</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_inline_publication_rejects_incomplete_legacy_projects_but_allows_unpublishing(): void
    {
        $this->administrator();
        $draft = Project::factory()->create();
        Livewire::test(ListProjects::class)->call('updateTableColumnState', 'status', (string) $draft->id, 'published')
            ->assertNotified('No se pudo publicar el proyecto');
        $this->assertSame(ProjectStatus::Draft, $draft->fresh()->status);
        $draft->update(['status' => ProjectStatus::Published]);
        Livewire::test(ListProjects::class)->call('updateTableColumnState', 'status', (string) $draft->id, 'draft')->assertHasNoErrors();
        $this->assertSame(ProjectStatus::Draft, $draft->fresh()->status);
    }

    public function test_long_plain_text_is_preserved_without_truncation_after_escaping(): void
    {
        $this->administrator();
        $media = $this->image()->getFirstMedia('images');
        $description = str_repeat('&', 12000);
        Livewire::test(CreateProject::class)->fillForm([
            'title' => 'Descripción larga', 'description' => $description, 'image_id' => $media->id,
            'project_category_id' => ProjectCategory::factory()->create()->id,
        ])->call('create')->assertHasNoFormErrors();
        $project = Project::query()->firstOrFail();
        $this->assertSame($description, $project->description);
        Livewire::test(EditProject::class, ['record' => $project->slug])->fillForm(['description' => null])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('', $project->fresh()->description);
    }

    public function test_inline_status_respects_update_permission_and_enum_validation(): void
    {
        $user = $this->administrator();
        $project = Project::factory()->create();
        Livewire::test(ListProjects::class)->call('updateTableColumnState', 'status', (string) $project->id, 'invalid');
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
        foreach ([[Permission::ViewProject, true], [Permission::UpdateProject, false]] as [$permission, $granted]) {
            $model = PermissionModel::query()->firstOrCreate(['name' => $permission->value], [
                'label' => $permission->getLabel(), 'module' => $permission->module(), 'action' => $permission->action(),
            ]);
            RolePermission::query()->updateOrCreate(['role' => UserRole::Admin->value, 'permission_id' => $model->id], ['is_granted' => $granted]);
        }
        RolePermission::clearCache();
        Livewire::test(ListProjects::class)->call('updateTableColumnState', 'status', (string) $project->id, 'published');
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_public_catalog_hides_drafts_and_unused_categories_and_filters_all_pages(): void
    {
        $web = ProjectCategory::factory()->create(['name' => 'Web', 'slug' => 'web']);
        $app = ProjectCategory::factory()->create(['name' => 'Apps', 'slug' => 'apps']);
        $empty = ProjectCategory::factory()->create(['name' => 'Categoría vacía']);
        $draft = Project::factory()->create(['title' => 'Trabajo privado', 'project_category_id' => $empty->id]);
        Project::factory()->published()->count(10)->create(['project_category_id' => $web->id]);
        $last = Project::factory()->published()->create(['title' => 'Aplicación publicada', 'project_category_id' => $app->id]);
        $page = Livewire::test(Portfolio::class)->assertSee('Aplicación publicada')->assertDontSee('Trabajo privado')->assertDontSee('Categoría vacía');
        $page->call('gotoPage', 2)->assertDontSee('Aplicación publicada')
            ->call('filterByCategory', 'apps')->assertSet('paginators.page', 1)->assertSee('Aplicación publicada');
        $page->call('filterByCategory', 'web')->assertDontSee('Aplicación publicada');
        $page->call('filterByCategory', "web' OR 1=1 --")->assertSee('No hay trabajos publicados')->assertDontSee('Aplicación publicada');
    }

    public function test_modal_can_open_published_records_and_clear_selection_without_exposing_drafts(): void
    {
        $published = Project::factory()->published()->create(['title' => 'Trabajo público', 'client' => 'Cliente público', 'completed_at' => '2026-01-15']);
        $draft = Project::factory()->create(['title' => 'Trabajo privado']);
        Livewire::test(Portfolio::class)->assertDontSee('Cliente público')->call('openProject', $published->id)
            ->assertSet('selectedProjectId', $published->id)->assertSee('Cliente público')->assertSee('15/01/2026')
            ->call('closeProject')->assertSet('selectedProjectId', null)->assertDontSee('Cliente público');
        Livewire::test(Portfolio::class)->call('openProject', $draft->id)->assertNotFound();
        Livewire::test(Portfolio::class)->call('openProject', 999999)->assertNotFound();
        $this->get('/proyectos?proyecto='.$draft->slug)->assertNotFound()->assertDontSee('Trabajo privado');
    }

    public function test_modal_rechecks_publication_on_every_request(): void
    {
        $project = Project::factory()->published()->create(['client' => 'Información privada']);
        $page = Livewire::test(Portfolio::class)->call('openProject', $project->id)->assertSee('Información privada');
        $project->update(['status' => 'draft']);
        $page->call('$refresh')->assertNotFound()->assertDontSee('Información privada');
    }

    public function test_modal_omits_empty_fields_and_unsafe_legacy_links(): void
    {
        $project = Project::factory()->published()->create([
            'short_description' => null, 'content' => null, 'client' => null, 'completed_at' => null,
            'external_url' => 'javascript:alert(1)',
        ]);
        Livewire::test(Portfolio::class)->call('openProject', $project->id)->assertSee($project->title)
            ->assertDontSee('Cliente / Empresa')->assertDontSee('Fecha de realización')
            ->assertDontSee('Visitar proyecto')->assertDontSee('javascript:alert(1)');
        $project->update(['external_url' => 'https://example.test/work']);
        Livewire::test(Portfolio::class)->call('openProject', $project->id)->assertSee('Visitar proyecto')
            ->assertSee('rel="noopener noreferrer"', false)->assertSee('target="_blank"', false);
    }

    public function test_unknown_legacy_links_and_malformed_query_parameters_are_rejected(): void
    {
        $this->get('/proyectos/no-existe')->assertNotFound();
        $this->get('/proyectos?categoria[]=web')->assertRedirect()->assertSessionHasErrors('categoria');
        $this->get('/proyectos?proyecto[]=privado')->assertRedirect()->assertSessionHasErrors('proyecto');
    }
}
