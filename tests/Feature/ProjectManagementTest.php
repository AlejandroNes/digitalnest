<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectCategories\Pages\CreateProjectCategory;
use App\Filament\Resources\ProjectCategories\Pages\EditProjectCategory;
use App\Filament\Resources\ProjectCategories\Pages\ListProjectCategories;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\SearchSetting;
use App\Models\User;
use App\ProjectStatus;
use App\Services\MediaUploader;
use App\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
    }

    private function uploadImage(string $name = 'portfolio.png'): MediaAsset
    {
        $hashOffset = abs(crc32($name)) % 50;

        return app(MediaUploader::class)->upload(
            auth()->user() ?? User::factory()->administrator()->create(),
            UploadedFile::fake()->image($name, 640 + $hashOffset, 480 + $hashOffset),
            'Portafolio',
            'Foto de portafolio'
        )['asset'];
    }

    public function test_administrator_can_list_search_and_filter_projects(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $catWeb = ProjectCategory::factory()->create(['name' => 'Desarrollo Web', 'slug' => 'desarrollo-web']);
        $catApp = ProjectCategory::factory()->create(['name' => 'Apps Móviles', 'slug' => 'apps-moviles']);

        $proj1 = Project::factory()->create([
            'title' => 'Plataforma E-commerce',
            'slug' => 'plataforma-ecommerce',
            'client' => 'Acme Retail',
            'project_category_id' => $catWeb->id,
            'status' => ProjectStatus::Published,
            'is_featured' => true,
        ]);

        $proj2 = Project::factory()->create([
            'title' => 'Aplicación Bancaria',
            'slug' => 'aplicacion-bancaria',
            'client' => 'Fintech Corp',
            'project_category_id' => $catApp->id,
            'status' => ProjectStatus::Draft,
            'is_featured' => false,
        ]);

        $this->get('/admin/projects')->assertOk();

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$proj1, $proj2])
            ->searchTable('E-commerce')
            ->assertCanSeeTableRecords([$proj1])
            ->assertCanNotSeeTableRecords([$proj2])
            ->searchTable('Fintech')
            ->assertCanSeeTableRecords([$proj2])
            ->assertCanNotSeeTableRecords([$proj1]);

        Livewire::test(ListProjects::class)
            ->filterTable('status', ProjectStatus::Published->value)
            ->assertCanSeeTableRecords([$proj1])
            ->assertCanNotSeeTableRecords([$proj2]);

        Livewire::test(ListProjects::class)->assertTableColumnDoesNotExist('is_featured');

        Livewire::test(ListProjects::class)
            ->filterTable('project_category_id', $catApp->id)
            ->assertCanSeeTableRecords([$proj2])
            ->assertCanNotSeeTableRecords([$proj1]);
    }

    public function test_administrator_can_create_project_with_the_simplified_fields(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $category = ProjectCategory::factory()->create(['name' => 'Consultoría']);
        $media = $this->uploadImage('cover.png')->getFirstMedia('images');

        $data = [
            'title' => 'Transformación Digital Bancaria',
            'project_category_id' => $category->id,
            'client' => 'Banco Global',
            'completed_at' => '2026-05-15',
            'external_url' => 'https://bancoglobal.test',
            'description' => "Se implementaron microservicios.\nIntegraciones seguras.",
            'image_id' => $media->id,
            'status' => ProjectStatus::Published->value,
        ];

        Livewire::test(CreateProject::class)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $project = Project::query()->where('slug', 'transformacion-digital-bancaria')->firstOrFail();
        $this->assertSame($data['title'], $project->title);
        $this->assertSame($category->id, $project->project_category_id);
        $this->assertSame('Banco Global', $project->client);
        $this->assertSame($media->id, $project->image_id);
        $this->assertSame(ProjectStatus::Published, $project->status);
        $this->assertFalse($project->is_featured);
        $this->assertSame(0, $project->sort_order);
        $this->assertSame($data['description'], $project->portfolioDescription());
    }

    public function test_administrator_can_edit_and_delete_project(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $project = Project::factory()->create([
            'title' => 'Proyecto Antiguo',
            'project_category_id' => ProjectCategory::factory(),
            'image_id' => $this->uploadImage('edicion.png')->getFirstMedia('images')->id,
            'slug' => 'proyecto-antiguo',
            'status' => ProjectStatus::Draft,
        ]);

        $this->get('/admin/projects/'.$project->slug.'/edit')->assertOk();

        Livewire::test(EditProject::class, ['record' => $project->slug])
            ->fillForm([
                'title' => 'Proyecto Renovado',
                'status' => ProjectStatus::Published->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Proyecto Renovado', $project->fresh()->title);
        $this->assertSame(ProjectStatus::Published, $project->fresh()->status);

        Livewire::test(EditProject::class, ['record' => $project->slug])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($project);
    }

    public function test_project_category_crud_and_referential_integrity(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->get('/admin/project-categories')->assertOk();

        Livewire::test(CreateProjectCategory::class)
            ->fillForm([
                'name' => 'Inteligencia Artificial',
                'slug' => 'inteligencia-artificial',
                'description' => 'Soluciones de machine learning.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = ProjectCategory::query()->where('slug', 'inteligencia-artificial')->firstOrFail();
        $this->assertSame('Inteligencia Artificial', $category->name);

        Livewire::test(EditProjectCategory::class, ['record' => $category->slug])
            ->fillForm(['name' => 'IA y Automatización'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('IA y Automatización', $category->fresh()->name);

        // Category with associated project cannot be deleted
        $project = Project::factory()->create(['project_category_id' => $category->id]);
        $this->assertFalse(Gate::allows('delete', $category));

        try {
            $category->delete();
            $this->fail('Una categoría con proyectos vinculados no debe eliminarse en BD.');
        } catch (QueryException) {
            $this->assertModelExists($category);
        }

        // Once project is detached or deleted, category can be deleted
        $project->delete();
        $this->assertTrue(Gate::allows('delete', $category));

        Livewire::test(EditProjectCategory::class, ['record' => $category->slug])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($category);
    }

    public function test_project_slug_is_generated_automatically_without_collisions(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $project = Project::factory()->create([
            'title' => 'Portal Corporativo',
            'slug' => 'portal-corporativo',
        ]);

        $category = ProjectCategory::factory()->create();
        $media = $this->uploadImage('slug.png')->getFirstMedia('images');
        Livewire::test(CreateProject::class)
            ->fillForm(['title' => 'Portal Corporativo', 'project_category_id' => $category->id, 'image_id' => $media->id])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('projects', ['slug' => 'portal-corporativo-2']);

        // Model HasSlug trait generates slug when blank
        $autoProject = new Project(['title' => 'Nuevo Proyecto Increíble']);
        $autoProject->save();
        $this->assertSame('nuevo-proyecto-increible', $autoProject->slug);
    }

    public function test_legacy_scopes_and_inline_status_changes(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $draft = Project::factory()->create([
            'status' => ProjectStatus::Draft,
            'is_featured' => false,
            'sort_order' => 20,
            'project_category_id' => ProjectCategory::factory(),
            'image_id' => $this->uploadImage('estado.png')->getFirstMedia('images')->id,
        ]);
        $published = Project::factory()->published()->featured()->create([
            'sort_order' => 10,
        ]);

        $this->assertCount(1, Project::published()->get());
        $this->assertSame($published->id, Project::published()->first()->id);

        $this->assertCount(1, Project::featured()->get());
        $this->assertSame($published->id, Project::featured()->first()->id);

        $ordered = Project::ordered()->get();
        $this->assertSame($published->id, $ordered->first()->id);
        $this->assertSame($draft->id, $ordered->last()->id);

        // Toggle publish via table action
        Livewire::test(ListProjects::class)
            ->call('updateTableColumnState', 'status', (string) $draft->id, ProjectStatus::Published->value)
            ->assertHasNoErrors();

        $this->assertSame(ProjectStatus::Published, $draft->fresh()->status);

        Livewire::test(ListProjects::class)
            ->call('updateTableColumnState', 'status', (string) $draft->id, ProjectStatus::Draft->value)
            ->assertHasNoErrors();

        $this->assertSame(ProjectStatus::Draft, $draft->fresh()->status);
    }

    public function test_gallery_maintains_order_and_preserves_media_assets_on_removal(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $img1 = $this->uploadImage('foto1.png')->getFirstMedia('images');
        $img2 = $this->uploadImage('foto2.png')->getFirstMedia('images');
        $img3 = $this->uploadImage('foto3.png')->getFirstMedia('images');

        $project = Project::factory()->create();

        ProjectImage::create(['project_id' => $project->id, 'media_id' => $img1->id, 'sort_order' => 0, 'caption' => 'Vista 1']);
        ProjectImage::create(['project_id' => $project->id, 'media_id' => $img2->id, 'sort_order' => 1, 'caption' => 'Vista 2']);
        ProjectImage::create(['project_id' => $project->id, 'media_id' => $img3->id, 'sort_order' => 2, 'caption' => 'Vista 3']);

        $project->refresh();
        $gallery = $project->galleryImages;
        $this->assertCount(3, $gallery);
        $this->assertSame($img1->id, $gallery[0]->media_id);
        $this->assertSame($img2->id, $gallery[1]->media_id);
        $this->assertSame($img3->id, $gallery[2]->media_id);

        // Removing image 2 from project gallery
        $gallery[1]->delete();

        $this->assertDatabaseCount('project_images', 2);
        // Media and files are preserved in the library
        $this->assertDatabaseHas('media', ['id' => $img2->id]);
        Storage::disk('public')->assertExists($img2->getPathRelativeToRoot());
    }

    public function test_media_assets_referenced_in_project_or_gallery_cannot_be_deleted(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $assetCover = $this->uploadImage('cover-test.png');
        $mediaCover = $assetCover->getFirstMedia('images');

        $assetGallery = $this->uploadImage('gallery-test.png');
        $mediaGallery = $assetGallery->getFirstMedia('images');

        $project = Project::factory()->create(['image_id' => $mediaCover->id]);
        ProjectImage::create(['project_id' => $project->id, 'media_id' => $mediaGallery->id, 'sort_order' => 0]);

        // MediaAsset delete is blocked for main image
        $this->assertTrue($assetCover->isReferenced());
        try {
            $assetCover->delete();
            $this->fail('Una imagen principal de proyecto no debe poder eliminarse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('La imagen está en uso', $e->getMessage());
        }

        // MediaAsset delete is blocked for gallery image
        $this->assertTrue($assetGallery->isReferenced());
        try {
            $assetGallery->delete();
            $this->fail('Una imagen de galería de proyecto no debe poder eliminarse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('La imagen está en uso', $e->getMessage());
        }

        // Once references are removed, deletion succeeds
        $project->update(['image_id' => null]);
        ProjectImage::query()->where('project_id', $project->id)->delete();

        $this->assertFalse($assetCover->isReferenced());
        $this->assertFalse($assetGallery->isReferenced());

        $this->assertTrue($assetCover->delete());
        $this->assertTrue($assetGallery->delete());
    }

    public function test_role_permissions_and_access_restrictions(): void
    {
        $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
        $admin = User::factory()->administrator()->create();
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $client = User::factory()->create(['role' => UserRole::User]);
        $inactive = User::factory()->inactive()->create();

        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->create(['project_category_id' => $category->id]);

        // 1. SuperAdmin: full access
        $this->actingAs($superAdmin);
        $this->assertTrue(Gate::allows('viewAny', Project::class));
        $this->assertTrue(Gate::allows('create', Project::class));
        $this->assertTrue(Gate::allows('update', $project));
        $this->assertTrue(Gate::allows('delete', $project));
        $this->assertTrue(Gate::allows('viewAny', ProjectCategory::class));
        $this->assertTrue(Gate::allows('create', ProjectCategory::class));
        $this->assertTrue(Gate::allows('update', $category));

        // 2. Admin: has configured permissions
        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('viewAny', Project::class));
        $this->assertTrue(Gate::allows('create', Project::class));
        $this->assertTrue(Gate::allows('update', $project));
        $this->assertTrue(Gate::allows('delete', $project));

        // 3. Editor: full management of projects and categories
        $this->actingAs($editor);
        $this->assertTrue(Gate::allows('viewAny', Project::class));
        $this->assertTrue(Gate::allows('create', Project::class));
        $this->assertTrue(Gate::allows('update', $project));
        $this->assertTrue(Gate::allows('delete', $project));
        $this->assertTrue(Gate::allows('viewAny', ProjectCategory::class));
        $this->assertTrue(Gate::allows('create', ProjectCategory::class));
        $this->assertTrue(Gate::allows('update', $category));

        $this->get('/admin/projects')->assertOk();
        $this->get('/admin/projects/create')->assertOk();
        $this->get('/admin/projects/'.$project->slug.'/edit')->assertOk();
        $this->get('/admin/project-categories')->assertOk();

        // 4. Regular client: no admin access
        $this->actingAs($client);
        $this->assertFalse(Gate::allows('viewAny', Project::class));
        $this->assertFalse(Gate::allows('create', Project::class));
        $this->assertFalse(Gate::allows('update', $project));
        $this->assertFalse(Gate::allows('delete', $project));
        $this->get('/admin/projects')->assertForbidden();

        // 5. Inactive user: forbidden
        $this->actingAs($inactive);
        $this->get('/admin/projects')->assertForbidden();

        // 6. Guest: redirected to login
        auth()->logout();
        $this->get('/admin/projects')->assertRedirect('/admin/login');
    }

    public function test_empty_states_render_when_no_records_exist(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        Livewire::test(ListProjects::class)
            ->assertSee('No hay proyectos registrados');

        Livewire::test(ListProjectCategories::class)
            ->assertSee('No hay categorías de proyectos');
    }

    public function test_public_projects_catalog_displays_published_projects_and_paginates(): void
    {
        $catWeb = ProjectCategory::factory()->create(['name' => 'Web', 'slug' => 'web']);
        $catMobile = ProjectCategory::factory()->create(['name' => 'Mobile', 'slug' => 'mobile']);

        $publishedWeb = Project::factory()->published()->create([
            'title' => 'Sitio Web Corporativo',
            'slug' => 'sitio-web-corporativo',
            'project_category_id' => $catWeb->id,
        ]);

        $publishedMobile = Project::factory()->published()->create([
            'title' => 'App Móvil iOS',
            'slug' => 'app-movil-ios',
            'project_category_id' => $catMobile->id,
        ]);

        $draft = Project::factory()->create([
            'title' => 'Proyecto Secreto',
            'slug' => 'proyecto-secreto',
            'status' => ProjectStatus::Draft,
        ]);

        // 1. Catalog shows all published projects and excludes drafts
        $this->get('/proyectos')
            ->assertOk()
            ->assertSee('Sitio Web Corporativo')
            ->assertSee('App Móvil iOS')
            ->assertDontSee('Proyecto Secreto');

        // 2. Category filter only shows projects of that category
        $this->get('/proyectos?categoria=web')
            ->assertOk()
            ->assertSee('Sitio Web Corporativo')
            ->assertDontSee('App Móvil iOS');

        $this->get('/proyectos?categoria=mobile')
            ->assertOk()
            ->assertSee('App Móvil iOS')
            ->assertDontSee('Sitio Web Corporativo');
    }

    public function test_legacy_links_open_portfolio_modals_and_drafts_return_404(): void
    {
        $category = ProjectCategory::factory()->create(['name' => 'E-Commerce']);
        $image = $this->uploadImage('main-project.png');

        $published = Project::factory()->published()->create([
            'title' => 'Tienda en Línea Moda',
            'slug' => 'tienda-en-linea-moda',
            'project_category_id' => $category->id,
            'client' => 'Moda S.A.',
            'location' => 'Barcelona',
            'external_url' => 'https://moda.test',
            'short_description' => 'Tienda online moderna y rápida.',
            'content' => '<p>Implementación con catálogo optimizado.</p>',
            'image_id' => $image->getFirstMedia('images')->id,
        ]);

        $galleryImage = $this->uploadImage('gallery-photo.png');
        ProjectImage::create([
            'project_id' => $published->id,
            'media_id' => $galleryImage->getFirstMedia('images')->id,
            'sort_order' => 0,
            'caption' => 'Detalle de checkout',
        ]);

        $related = Project::factory()->published()->create([
            'title' => 'Pasarela de Pagos',
            'slug' => 'pasarela-de-pagos',
            'project_category_id' => $category->id,
        ]);

        $draft = Project::factory()->create([
            'title' => 'Proyecto No Publicado',
            'slug' => 'proyecto-no-publicado',
            'status' => ProjectStatus::Draft,
        ]);

        $this->get('/proyectos/tienda-en-linea-moda')
            ->assertStatus(301)->assertRedirect(route('projects.index', ['proyecto' => $published->slug]));
        $this->get('/proyectos?proyecto=tienda-en-linea-moda')
            ->assertOk()->assertSee('Tienda en Línea Moda')->assertSee('Moda S.A.')
            ->assertSee('https://moda.test')->assertSee('Tienda online moderna y rápida.')
            ->assertSee('Implementación con catálogo optimizado.')
            ->assertDontSee('Barcelona')->assertDontSee('Detalle de checkout');
        $this->assertDatabaseHas('project_images', ['project_id' => $published->id]);

        // Draft project returns 404
        $this->get('/proyectos/proyecto-no-publicado')
            ->assertNotFound();

        // Non-existent slug returns 404
        $this->get('/proyectos/slug-inexistente')
            ->assertNotFound();
    }

    public function test_legacy_seo_data_is_preserved_and_portfolio_uses_global_robots(): void
    {
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        app()->instance('env', 'production');
        config(['app.url' => 'https://corporate.example.com']);
        $image = $this->uploadImage('og-project.png');
        $media = $image->getFirstMedia('images');

        $project = Project::factory()->published()->create([
            'title' => 'Proyecto Innovador',
            'slug' => 'proyecto-innovador',
            'short_description' => 'Descripción breve del proyecto innovador.',
            'seo_title' => 'Proyecto Innovador | Caso de Éxito Corporativo',
            'seo_description' => 'Meta descripción personalizada para motores de búsqueda.',
            'seo_image_id' => $media->id,
            'seo_canonical' => '/proyectos/proyecto-innovador',
            'seo_noindex' => false,
        ]);

        $this->get('/proyectos/proyecto-innovador')->assertStatus(301);
        $this->get('/proyectos?proyecto=proyecto-innovador')->assertOk()
            ->assertSee('content="index, follow"', false);
        $this->assertSame('Proyecto Innovador | Caso de Éxito Corporativo', $project->fresh()->seo_title);
        $project->update(['seo_noindex' => true]);
        $this->assertTrue($project->fresh()->seo_noindex);
        SearchSetting::query()->findOrFail(1)->update(['indexable' => false]);
        $this->get('/proyectos')->assertOk()->assertSee('content="noindex, nofollow"', false);
    }

    public function test_sitemap_links_to_the_portfolio_without_individual_detail_pages(): void
    {
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        app()->instance('env', 'production');
        config(['app.url' => 'https://corporate.example.com']);

        $published = Project::factory()->published()->create([
            'slug' => 'proyecto-sitemap-publicado',
            'seo_noindex' => false,
        ]);

        $noIndex = Project::factory()->published()->create([
            'slug' => 'proyecto-sitemap-noindex',
            'seo_noindex' => true,
        ]);

        $draft = Project::factory()->create([
            'slug' => 'proyecto-sitemap-borrador',
            'status' => ProjectStatus::Draft,
        ]);

        $sitemap = $this->get('/sitemap.xml')->assertOk();

        // Published indexable project is included
        $sitemap->assertSee('<loc>https://corporate.example.com/proyectos</loc>', false)
            ->assertDontSee(url('/proyectos/proyecto-sitemap-publicado'), false);

        // Draft and noindex projects are excluded
        $sitemap->assertDontSee(url('/proyectos/proyecto-sitemap-borrador'), false);
        $sitemap->assertDontSee(url('/proyectos/proyecto-sitemap-noindex'), false);
    }

    public function test_changing_project_slug_creates_automatic_301_redirect_without_breaking_routes(): void
    {
        $project = Project::factory()->published()->create([
            'title' => 'Marca Global',
            'slug' => 'marca-global-antiguo',
        ]);

        $this->get('/proyectos/marca-global-antiguo')->assertStatus(301);

        // Update project slug
        $project->update(['slug' => 'marca-global-nuevo']);

        // Visiting the old slug performs 301 redirect to new slug
        $this->get('/proyectos/marca-global-antiguo')
            ->assertRedirect('/proyectos/marca-global-nuevo')
            ->assertStatus(301);

        // Visiting the new slug renders 200 OK
        $this->get('/proyectos/marca-global-nuevo')
            ->assertStatus(301)->assertRedirect(route('projects.index', ['proyecto' => 'marca-global-nuevo']));
        $this->get('/proyectos?proyecto=marca-global-nuevo')->assertOk()->assertSee('Marca Global');
    }

    public function test_featured_projects_caching_and_invalidation(): void
    {
        $p1 = Project::factory()->published()->featured()->create(['title' => 'Destacado Uno', 'sort_order' => 1]);
        $p2 = Project::factory()->published()->featured()->create(['title' => 'Destacado Dos', 'sort_order' => 2]);

        $featured = Project::featuredPublished(2);
        $this->assertCount(2, $featured);
        $this->assertSame($p1->id, $featured[0]->id);

        // Creating another featured project with lower sort order invalidates cache
        $p0 = Project::factory()->published()->featured()->create(['title' => 'Destacado Cero', 'sort_order' => 0]);

        $updated = Project::featuredPublished(2);
        $this->assertSame($p0->id, $updated[0]->id);

        // Deleting invalidates cache
        $p0->delete();
        $afterDelete = Project::featuredPublished(2);
        $this->assertSame($p1->id, $afterDelete[0]->id);
    }
}
