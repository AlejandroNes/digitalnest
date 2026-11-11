<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\Tool;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CorporateModulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.corporate_modules_enabled' => false]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_corporate_models_are_denied_even_to_the_super_administrator(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]));

        foreach ([Service::class, Testimonial::class, Faq::class, Project::class, ProjectCategory::class] as $model) {
            $this->assertFalse(Gate::allows('viewAny', $model), $model);
            $this->assertFalse(Gate::allows('create', $model), $model);
        }
        $this->assertTrue(Gate::allows('viewAny', Tool::class));

        foreach (['/admin/services', '/admin/projects', '/admin/testimonials', '/admin/faqs', '/admin/project-categories'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_corporate_modules_disappear_from_the_panel_navigation_and_dashboard(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin')->assertOk();

        $urls = collect(Filament::getNavigation())->flatMap(fn (NavigationGroup $group) => $group->getItems())
            ->map(fn (NavigationItem $item): string => (string) parse_url($item->getUrl(), PHP_URL_PATH));

        $this->assertContains('/admin/tools', $urls);
        foreach (['/admin/services', '/admin/projects', '/admin/testimonials', '/admin/faqs', '/admin/project-categories'] as $path) {
            $this->assertNotContains($path, $urls);
        }
    }

    public function test_public_corporate_pages_return_404_and_leave_the_sitemap(): void
    {
        $service = Service::factory()->create(['slug' => 'consultoria']);
        $project = Project::factory()->published()->create(['slug' => 'proyecto']);
        $this->assertTrue($service->exists && $project->exists);

        $this->get('/servicios/consultoria')->assertNotFound();
        $this->get('/proyectos')->assertNotFound();
        $this->get('/proyectos/proyecto')->assertNotFound();

        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        app()->instance('env', 'production');
        $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee('/servicios/consultoria', false)
            ->assertDontSee('/proyectos', false);
    }
}
