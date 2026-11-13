<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactAssistantSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\MailSettings;
use App\Filament\Pages\RolesAndPermissions;
use App\Filament\Pages\SearchSettings;
use App\Filament\Pages\SystemTools;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\LegalPages\LegalPageResource;
use App\Filament\Resources\LegalPages\Pages\CreateLegalPage;
use App\Filament\Resources\LegalPages\Pages\EditLegalPage;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PostCategories\PostCategoryResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AdministrationOverview;
use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\Services\ApplicationCache;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PanelNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_panel_registers_all_resources_pages_and_system_widget(): void
    {
        $panel = Filament::getPanel('admin');
        foreach ([UserResource::class, MediaAssetResource::class, PostResource::class, PostCategoryResource::class,
            ServiceResource::class, TestimonialResource::class, FaqResource::class, LegalPageResource::class,
            RedirectResource::class, ContactMessageResource::class] as $resource) {
            $this->assertContains($resource, $panel->getResources());
        }
        foreach ([Dashboard::class, GeneralSettings::class, SearchSettings::class, MailSettings::class, ContactAssistantSettings::class,
            RolesAndPermissions::class, SystemTools::class] as $page) {
            $this->assertContains($page, $panel->getPages());
        }
        $this->assertNotContains(\Filament\Pages\Dashboard::class, $panel->getPages());
        $this->assertContains(AdministrationOverview::class, $panel->getWidgets());
    }

    public function test_administrator_navigation_contains_all_modules_in_their_groups_and_links_work(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin')->assertOk();
        $groups = Filament::getNavigation();
        $this->assertSame([null, 'Ofertas', 'Herramientas', 'Flujos y plantillas', 'Blog y Noticias', 'Portafolio', 'Sitio Corporativo', 'Comunicación', 'SEO y Analítica', 'Configuración y Sistema'],
            array_values(array_map(fn (NavigationGroup $group): ?string => $group->getLabel(), $groups)));
        $expected = [
            '' => ['/admin'],
            'Ofertas' => ['/admin/deals', '/admin/rankings'],
            'Herramientas' => ['/admin/tools', '/admin/tool-categories'],
            'Flujos y plantillas' => ['/admin/workflows', '/admin/templates'],
            'Blog y Noticias' => ['/admin/posts', '/admin/post-categories'],
            'Portafolio' => ['/admin/projects', '/admin/project-categories'],
            'Sitio Corporativo' => ['/admin/services', '/admin/testimonials', '/admin/faqs', '/admin/legal-pages', '/admin/media-assets'],
            'Comunicación' => ['/admin/contact-messages', '/admin/contact-assistant-settings', '/admin/mail-settings'],
            'SEO y Analítica' => ['/admin/search-settings', '/admin/redirects'],
            'Configuración y Sistema' => ['/admin/general-settings', '/admin/users', '/admin/roles-and-permissions', '/admin/system-tools', '/admin/profile'],
        ];
        foreach ($groups as $group) {
            $urls = collect($group->getItems())->map(fn (NavigationItem $item): string => parse_url($item->getUrl(), PHP_URL_PATH))->all();
            $groupKey = (string) $group->getLabel();
            $this->assertEqualsCanonicalizing($expected[$groupKey], $urls);
            foreach ($group->getItems() as $item) {
                $this->get($item->getUrl())->assertOk();
            }
        }
    }

    public function test_editor_navigation_includes_content_and_profile_without_critical_modules(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertOk();
        $urls = collect(Filament::getNavigation())->flatMap(fn (NavigationGroup $group) => $group->getItems())
            ->map(fn (NavigationItem $item): string => parse_url($item->getUrl(), PHP_URL_PATH))->all();
        $this->assertEqualsCanonicalizing(['/admin', '/admin/media-assets', '/admin/posts', '/admin/post-categories',
            '/admin/projects', '/admin/project-categories', '/admin/tools', '/admin/tool-categories', '/admin/workflows', '/admin/templates',
            '/admin/services', '/admin/testimonials', '/admin/faqs', '/admin/profile', '/admin/deals', '/admin/rankings'], $urls);
    }

    #[TestWith(['guest'])]
    #[TestWith(['editor'])]
    #[TestWith(['inactive'])]
    public function test_critical_pages_reject_unauthorized_direct_access(string $role): void
    {
        if ($role !== 'guest') {
            $this->actingAs($role === 'editor' ? User::factory()->create() : User::factory()->administrator()->inactive()->create());
        }
        foreach (['users', 'roles-and-permissions', 'general-settings', 'search-settings', 'redirects', 'legal-pages', 'contact-messages', 'mail-settings', 'contact-assistant-settings', 'system-tools'] as $path) {
            $response = $this->get('/admin/'.$path);
            if ($role === 'guest') {
                $response->assertRedirect('/admin/login');
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_roles_page_explains_existing_permissions_and_links_to_role_assignment(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->get(RolesAndPermissions::getUrl())->assertOk()
            ->assertSee('Administrador')->assertSee('Editor')->assertSee('Enviar correo de prueba')
            ->assertSee('Denegado')->assertSee(UserResource::getUrl());
        $this->actingAs(User::factory()->create());
        Livewire::test(RolesAndPermissions::class)->assertForbidden();
    }

    public function test_system_tool_clears_only_application_keys_and_rechecks_permissions(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $component = Livewire::test(SystemTools::class);
        Cache::put(ApplicationCache::SEARCH, [], 300);
        Cache::put(ApplicationCache::PRIVACY_REVISION, 1, 300);
        Cache::put('unrelated.session', 'preservada', 300);
        $component->callAction('clearApplicationCache')->assertNotified();
        $this->assertFalse(Cache::has(ApplicationCache::SEARCH));
        $this->assertFalse(Cache::has(ApplicationCache::PRIVACY_REVISION));
        $this->assertSame('preservada', Cache::get('unrelated.session'));

        Cache::put(ApplicationCache::SEARCH, ['default_title' => 'Preservado'], 300);
        $admin->update(['role' => UserRole::Editor]);
        $component->call('clearApplicationCache')->assertForbidden();
        $this->assertSame(['default_title' => 'Preservado'], Cache::get(ApplicationCache::SEARCH));
        Livewire::test(SystemTools::class)->assertForbidden();
    }

    public function test_privacy_page_can_be_created_reopened_and_published_from_filament(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(CreateLegalPage::class)->fillForm([
            'type' => 'privacy', 'content' => '<p>Privacidad inicial.</p>', 'is_published' => false,
        ])->call('create')->assertHasNoFormErrors();
        $this->get('/legal/privacy')->assertNotFound();
        $component = Livewire::test(EditLegalPage::class, ['record' => 'privacy']);
        $this->assertSame('<p>Privacidad inicial.</p>', $component->instance()->form->getState()['content']);
        $component->fillForm(['content' => '<p>Privacidad publicada.</p>', 'is_published' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, LegalPage::query()->where('type', 'privacy')->sole()->revision);
        $this->get('/legal/privacy')->assertOk()->assertSee('Privacidad publicada.');
        Livewire::test(EditLegalPage::class, ['record' => 'privacy'])->assertSet('data.is_published', true);
    }

    public function test_saved_search_configuration_reloads_and_updates_public_metadata_and_cookie_notice(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(SearchSettings::class)->fillForm([
            'default_title' => 'Sitio actualizado', 'verification_token' => 'verificacion_actualizada', 'verification_enabled' => true,
            'cookies_enabled' => true, 'cookie_message' => 'Aviso actualizado.',
        ])->call('save')->assertHasNoFormErrors();
        Livewire::test(SearchSettings::class)->assertSet('data.default_title', 'Sitio actualizado')
            ->assertSet('data.verification_token', 'verificacion_actualizada')
            ->assertSet('data.cookie_message', 'Aviso actualizado.');
        $this->get('/')->assertOk()->assertSee('<title>Sitio actualizado</title>', false)
            ->assertSee('name="google-site-verification" content="verificacion_actualizada"', false)
            ->assertSee('Aviso actualizado.');
    }

    public function test_dashboard_shows_real_counts_and_limits_sensitive_indicators_to_administrators(): void
    {
        $admin = User::factory()->administrator()->create();
        Post::factory()->count(3)->create();
        Post::factory()->published()->count(2)->create();
        Service::factory()->count(4)->create();
        Service::factory()->inactive()->create();
        ContactMessage::factory()->count(3)->create();
        ContactMessage::factory()->create(['status' => 'read']);
        $this->actingAs($admin);
        $component = Livewire::test(AdministrationOverview::class)->assertSee('Mensajes pendientes');
        $stats = collect($component->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())->keyBy(fn ($stat): string => $stat->getLabel());
        $this->assertSame(5, $stats['Artículos']->getValue());
        $this->assertSame('2 publicados', $stats['Artículos']->getDescription());
        $this->assertSame(5, $stats['Servicios']->getValue());
        $this->assertSame('4 activos', $stats['Servicios']->getDescription());
        $this->assertSame(3, $stats['Mensajes pendientes']->getValue());
        $this->get('/admin')->assertOk()->assertSee('Mensajes pendientes');

        $this->actingAs(User::factory()->create());
        Livewire::test(AdministrationOverview::class)->assertSee('Artículos')->assertSee('Servicios')
            ->assertDontSee('Mensajes pendientes')->assertDontSee('Administradores activos')->assertDontSee('Usuarios');
        $this->actingAs(User::factory()->administrator()->inactive()->create());
        Livewire::test(AdministrationOverview::class)->assertForbidden();
    }
}
