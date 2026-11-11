<?php

use App\ContactMessageStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\SiteSummaryWidget;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\PostStatus;
use App\UserRole;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('renders dashboard successfully with contextual greeting and site name in subheading', function (): void {
    $admin = User::factory()->administrator()->create(['name' => 'Alejandro']);
    $setting = GeneralSetting::firstOrNew(['id' => 1]);
    $setting->company_name = 'Corporativo Innova';
    $setting->save();

    $this->actingAs($admin);

    Carbon::setTestNow('2026-10-15 08:30:00');
    $this->get('/admin')->assertOk()
        ->assertSee('Buenos días, Alejandro')
        ->assertSee('Corporativo Innova');

    Carbon::setTestNow('2026-10-15 14:30:00');
    $this->get('/admin')->assertOk()
        ->assertSee('Buenas tardes, Alejandro');

    Carbon::setTestNow('2026-10-15 22:30:00');
    $this->get('/admin')->assertOk()
        ->assertSee('Buenas noches, Alejandro');

    Carbon::setTestNow();
});

it('calculates real counts correctly across all site summary stats', function (): void {
    $admin = User::factory()->administrator()->create();

    ContactMessage::factory()->count(3)->create(['status' => ContactMessageStatus::Pending]);
    ContactMessage::factory()->count(2)->create(['status' => ContactMessageStatus::Read]);

    Post::factory()->count(4)->create([
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(2),
    ]);
    Post::factory()->count(2)->create([
        'status' => PostStatus::Draft,
        'published_at' => null,
    ]);

    Service::factory()->count(5)->create(['is_active' => true]);
    Service::factory()->count(2)->create(['is_active' => false]);

    $this->actingAs($admin);

    $component = Livewire::test(SiteSummaryWidget::class);
    $stats = collect($component->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())
        ->keyBy(fn ($stat): string => $stat->getLabel());

    expect($stats['Mensajes pendientes']->getValue())->toBe(3)
        ->and($stats['Artículos publicados']->getValue())->toBe(4)
        ->and($stats['Borradores']->getValue())->toBe(2)
        ->and($stats['Servicios activos']->getValue())->toBe(5);
});

it('excludes drafts and future scheduled articles from published count', function (): void {
    $admin = User::factory()->administrator()->create();

    Post::factory()->create([
        'title' => 'Publicado pasado',
        'status' => PostStatus::Published,
        'published_at' => now()->subHours(2),
    ]);

    Post::factory()->create([
        'title' => 'Programado futuro',
        'status' => PostStatus::Published,
        'published_at' => now()->addDays(3),
    ]);

    Post::factory()->create([
        'title' => 'Borrador con fecha',
        'status' => PostStatus::Draft,
        'published_at' => now()->subDay(),
    ]);

    Post::factory()->create([
        'title' => 'Borrador futuro',
        'status' => PostStatus::Draft,
        'published_at' => now()->addDay(),
    ]);

    Post::factory()->create([
        'title' => 'Borrador sin fecha',
        'status' => PostStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(SiteSummaryWidget::class);
    $stats = collect($component->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())
        ->keyBy(fn ($stat): string => $stat->getLabel());

    expect($stats['Artículos publicados']->getValue())->toBe(1)
        ->and($stats['Borradores']->getValue())->toBe(3);
});

it('limits widget and indicator visibility according to user permissions', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    Post::factory()->count(2)->create(['status' => PostStatus::Published, 'published_at' => now()->subDay()]);
    Service::factory()->count(2)->create(['is_active' => true]);
    ContactMessage::factory()->count(3)->create(['status' => ContactMessageStatus::Pending]);

    $this->actingAs($editor);

    $summary = Livewire::test(SiteSummaryWidget::class)
        ->assertSee('Artículos publicados')
        ->assertSee('Borradores')
        ->assertSee('Servicios activos')
        ->assertDontSee('Mensajes pendientes');

    $summaryStats = collect($summary->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())
        ->map(fn ($stat): string => $stat->getLabel())->all();

    expect($summaryStats)->not->toContain('Mensajes pendientes')
        ->and($summaryStats)->toContain('Artículos publicados', 'Borradores', 'Servicios activos');

    Livewire::test(RecentActivityWidget::class)
        ->assertSee('Artículos recientes')
        ->assertDontSee('Últimos mensajes recibidos');

    Livewire::test(QuickActionsWidget::class)
        ->assertSee('Crear artículo')
        ->assertSee('Subir imagen')
        ->assertSee('Crear servicio')
        ->assertDontSee('Ver mensajes')
        ->assertDontSee('Configuración general');

    $inactive = User::factory()->administrator()->inactive()->create();
    $this->actingAs($inactive);

    Livewire::test(SiteSummaryWidget::class)->assertForbidden();
    Livewire::test(RecentActivityWidget::class)->assertForbidden();
    Livewire::test(QuickActionsWidget::class)->assertForbidden();
});

it('limits recent activity to 5 records and orders descending by date', function (): void {
    $admin = User::factory()->administrator()->create();

    $messages = [];
    for ($i = 1; $i <= 7; $i++) {
        $messages[] = ContactMessage::factory()->create([
            'name' => "Contacto {$i}",
            'created_at' => now()->subHours(10 - $i),
        ]);
    }

    $posts = [];
    for ($i = 1; $i <= 7; $i++) {
        $post = Post::factory()->create([
            'title' => "Artículo {$i}",
            'updated_at' => now()->subHours(10 - $i),
        ]);
        $posts[] = $post;
    }

    $this->actingAs($admin);

    $component = Livewire::test(RecentActivityWidget::class);
    $viewData = $component->instance()->render()->getData();

    expect($viewData['recentMessages'])->toHaveCount(5)
        ->and($viewData['recentMessages']->first()->name)->toBe('Contacto 7')
        ->and($viewData['recentMessages']->last()->name)->toBe('Contacto 3')
        ->and($viewData['recentPosts'])->toHaveCount(5)
        ->and($viewData['recentPosts']->first()->title)->toBe('Artículo 7')
        ->and($viewData['recentPosts']->last()->title)->toBe('Artículo 3');

    $component
        ->assertSee('Contacto 7')
        ->assertSee('Contacto 3')
        ->assertDontSee('Contacto 1')
        ->assertDontSee('Contacto 2')
        ->assertSee('Artículo 7')
        ->assertSee('Artículo 3')
        ->assertDontSee('Artículo 1')
        ->assertDontSee('Artículo 2');
});

it('handles empty database states gracefully without errors', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);

    $summary = Livewire::test(SiteSummaryWidget::class);
    $stats = collect($summary->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())
        ->keyBy(fn ($stat): string => $stat->getLabel());

    expect($stats['Mensajes pendientes']->getValue())->toBe(0)
        ->and($stats['Artículos publicados']->getValue())->toBe(0)
        ->and($stats['Borradores']->getValue())->toBe(0)
        ->and($stats['Servicios activos']->getValue())->toBe(0);

    Livewire::test(RecentActivityWidget::class)
        ->assertSee('No hay mensajes de contacto')
        ->assertSee('No hay artículos registrados');

    $this->get('/admin')->assertOk()
        ->assertSee('No hay mensajes de contacto')
        ->assertSee('No hay artículos registrados');
});

it('provides authorized quick actions pointing to valid Filament routes', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);

    $component = Livewire::test(QuickActionsWidget::class);
    $viewData = $component->instance()->render()->getData();
    $actions = collect($viewData['actions'])->keyBy('label');

    expect($actions->keys()->all())->toEqualCanonicalizing([
        'Crear artículo',
        'Subir imagen',
        'Crear servicio',
        'Crear proyecto',
        'Ver mensajes',
        'Configuración general',
    ]);

    expect($actions['Crear artículo']['url'])->toBe(PostResource::getUrl('create'))
        ->and($actions['Subir imagen']['url'])->toBe(MediaAssetResource::getUrl('create'))
        ->and($actions['Crear servicio']['url'])->toBe(ServiceResource::getUrl('create'))
        ->and($actions['Crear proyecto']['url'])->toBe(ProjectResource::getUrl('create'))
        ->and($actions['Ver mensajes']['url'])->toBe(ContactMessageResource::getUrl())
        ->and($actions['Configuración general']['url'])->toBe(GeneralSettings::getUrl());

    $this->get($actions['Crear artículo']['url'])->assertOk();
    $this->get($actions['Subir imagen']['url'])->assertOk();
    $this->get($actions['Crear servicio']['url'])->assertOk();
    $this->get($actions['Crear proyecto']['url'])->assertOk();
    $this->get($actions['Ver mensajes']['url'])->assertOk();
    $this->get($actions['Configuración general']['url'])->assertOk();
});

it('registers new widgets and respects dashboard widget composition', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getWidgets())->toContain(SiteSummaryWidget::class)
        ->and($panel->getWidgets())->toContain(RecentActivityWidget::class)
        ->and($panel->getWidgets())->toContain(QuickActionsWidget::class);

    $dashboard = new Dashboard;
    expect($dashboard->getWidgets())->toBe([
        SiteSummaryWidget::class,
        RecentActivityWidget::class,
        QuickActionsWidget::class,
    ]);
});
