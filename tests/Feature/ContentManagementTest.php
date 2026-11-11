<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\LegalPage;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public static function modules(): array
    {
        return [
            'categories' => [PostCategory::class, 'PostCategories', 'post-categories', ['name' => 'Categoría', 'slug' => 'categoria'], 'name', 'Categoría nueva'],
            'posts' => [Post::class, 'Posts', 'posts', ['title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido.</p>', 'status' => 'draft'], 'title', 'Artículo nuevo'],
            'services' => [Service::class, 'Services', 'services', ['name' => 'Servicio', 'slug' => 'servicio', 'description' => '<p>Descripción.</p>', 'sort_order' => 0, 'is_active' => true], 'name', 'Servicio nuevo'],
            'testimonials' => [Testimonial::class, 'Testimonials', 'testimonials', ['name' => 'Cliente', 'quote' => 'Una experiencia excelente.', 'sort_order' => 0, 'is_active' => true], 'name', 'Cliente nuevo'],
            'faqs' => [Faq::class, 'Faqs', 'faqs', ['question' => '¿Cómo funciona?', 'answer' => '<p>Así funciona.</p>', 'sort_order' => 0, 'is_active' => true], 'question', '¿Cómo empiezo?'],
            'legal pages' => [LegalPage::class, 'LegalPages', 'legal-pages', ['type' => 'terms', 'content' => '<p>Términos iniciales.</p>', 'is_published' => false], 'content', '<p>Términos nuevos.</p>'],
        ];
    }

    private function page(string $module, string $prefix, string $model): string
    {
        return 'App\\Filament\\Resources\\'.$module.'\\Pages\\'.$prefix.class_basename($model);
    }

    #[DataProvider('modules')]
    public function test_administrator_can_create_edit_and_delete_content(string $model, string $module, string $path, array $data, string $field, string $updated): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin/'.$path)->assertOk();
        Livewire::test($this->page($module, 'Create', $model))->fillForm($data)->call('create')->assertHasNoFormErrors();
        $record = $model::query()->firstOrFail();
        $this->assertSame($data[$field], $record->getAttribute($field));
        $key = $record->getRawOriginal($record->getRouteKeyName());
        $this->get('/admin/'.$path.'/'.$key.'/edit')->assertOk();
        Livewire::test($this->page($module, 'Edit', $model), ['record' => $key])->fillForm([$field => $updated])->call('save')->assertHasNoFormErrors();
        $this->assertSame($updated, $record->fresh()->getAttribute($field));
        Livewire::test($this->page($module, 'Edit', $model), ['record' => $key])->callAction(DeleteAction::class);
        $this->assertModelMissing($record);
    }

    #[DataProvider('modules')]
    public function test_guests_and_inactive_users_cannot_access_content_urls(string $model, string $module, string $path, array $data, string $field, string $updated): void
    {
        $record = $model::factory()->create();
        $key = $record->getRawOriginal($record->getRouteKeyName());
        $this->get('/admin/'.$path)->assertRedirect('/admin/login');
        $this->get('/admin/'.$path.'/create')->assertRedirect('/admin/login');
        $this->get('/admin/'.$path.'/'.$key.'/edit')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->inactive()->create());
        $this->get('/admin/'.$path)->assertForbidden();
        $this->get('/admin/'.$path.'/create')->assertForbidden();
        $this->get('/admin/'.$path.'/'.$key.'/edit')->assertForbidden();
    }

    #[DataProvider('modules')]
    public function test_editor_permissions_are_enforced_by_policies_and_direct_urls(string $model, string $module, string $path, array $data, string $field, string $updated): void
    {
        $editor = User::factory()->create();
        $record = $model::factory()->create();
        $key = $record->getRawOriginal($record->getRouteKeyName());
        $this->actingAs($editor);
        $allowed = $model !== LegalPage::class;
        $this->assertSame($allowed, Gate::allows('viewAny', $model));
        $this->assertSame($allowed, Gate::allows('create', $model));
        $this->assertSame($allowed, Gate::allows('view', $record));
        $this->assertSame($allowed, Gate::allows('update', $record));
        $this->assertFalse(Gate::allows('delete', $record));
        $this->get('/admin/'.$path)->assertStatus($allowed ? 200 : 403);
        $this->get('/admin/'.$path.'/create')->assertStatus($allowed ? 200 : 403);
        $this->get('/admin/'.$path.'/'.$key.'/edit')->assertStatus($allowed ? 200 : 403);
    }

    #[DataProvider('modules')]
    public function test_required_content_fields_are_validated(string $model, string $module, string $path, array $data, string $field, string $updated): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test($this->page($module, 'Create', $model))->fillForm([$field => ''])->call('create')->assertHasFormErrors([$field]);
        $this->assertDatabaseCount((new $model)->getTable(), 0);
    }

    public function test_post_categories_are_persisted_and_cannot_be_deleted_while_in_use(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = PostCategory::factory()->create();
        Livewire::test('App\\Filament\\Resources\\Posts\\Pages\\CreatePost')->fillForm([
            'title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido.</p>', 'status' => 'published',
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'categories' => [$category->id],
        ])->call('create')->assertHasNoFormErrors();
        $post = Post::query()->firstOrFail();
        $this->assertSame([$category->id], $post->categories->modelKeys());
        $this->assertFalse(Gate::allows('delete', $category));
        try {
            $category->delete();
            $this->fail('Una categoría referenciada no debe eliminarse.');
        } catch (QueryException $exception) {
            $this->assertModelExists($category);
        }
        $post->delete();
        $this->assertDatabaseCount('post_category_post', 0);
        $this->assertTrue(Gate::allows('delete', $category));
    }

    public function test_forged_category_ids_do_not_create_a_post(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test('App\\Filament\\Resources\\Posts\\Pages\\CreatePost')->fillForm([
            'title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido.</p>',
            'status' => 'draft', 'categories' => [999],
        ])->call('create')->assertHasFormErrors();

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_category_post', 0);
    }

    public function test_public_post_queries_exclude_drafts_undated_and_future_posts_and_paginate(): void
    {
        $this->freezeTime();
        Post::factory()->create();
        Post::factory()->create(['status' => 'published', 'published_at' => null]);
        Post::factory()->published()->create(['published_at' => now()->addDay()]);
        $first = Post::factory()->published()->create(['published_at' => now()->subDay()]);
        $newest = Post::factory()->published()->create();
        $page = Post::published()->newest()->with('categories', 'image.model')->paginate(1);
        $this->assertSame(2, $page->total());
        $this->assertSame([$newest->id], $page->getCollection()->modelKeys());
        $this->assertSame([$first->id], Post::published()->newest()->paginate(1, page: 2)->getCollection()->modelKeys());
    }

    #[TestWith([Service::class])]
    #[TestWith([Testimonial::class])]
    #[TestWith([Faq::class])]
    public function test_active_content_is_ordered_with_stable_ties(string $model): void
    {
        $model::factory()->inactive()->create(['sort_order' => 0]);
        $last = $model::factory()->create(['sort_order' => 10]);
        $first = $model::factory()->create(['sort_order' => 1]);
        $tie = $model::factory()->create(['sort_order' => 1]);
        $this->assertSame([$first->id, $tie->id, $last->id], $model::active()->ordered()->get()->modelKeys());
    }

    #[TestWith([Post::class, 'content'])]
    #[TestWith([Service::class, 'description'])]
    #[TestWith([Faq::class, 'answer'])]
    #[TestWith([LegalPage::class, 'content'])]
    public function test_rich_html_is_sanitized_when_saved_outside_filament(string $model, string $field): void
    {
        $record = $model::factory()->create([$field => '<p style="position:fixed" onclick="evil()">Texto <strong>seguro</strong></p><script>alert(1)</script><img src=x onerror=evil()><a href="javascript:evil()">enlace</a><iframe src="https://example.test"></iframe>']);
        $html = $record->fresh()->getAttribute($field);
        $this->assertStringContainsString('<strong>seguro</strong>', $html);
        foreach (['<script', 'onclick', 'onerror', 'javascript:', '<iframe', '<img', 'style='] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }

    public function test_slugs_are_generated_once_and_resolve_by_route_key(): void
    {
        $post = Post::factory()->create(['title' => 'Título del artículo', 'slug' => null]);
        $this->assertSame('titulo-del-articulo', $post->slug);
        $post->update(['title' => 'Otro título']);
        $this->assertSame('titulo-del-articulo', $post->fresh()->slug);
        $this->assertSame($post->id, (new Post)->resolveRouteBinding('titulo-del-articulo')->id);
    }

    #[TestWith(['duplicate', 'unique'])]
    #[TestWith(['Invalid / slug', 'regex'])]
    public function test_invalid_or_duplicate_slugs_are_rejected(string $slug, string $rule): void
    {
        Post::factory()->create(['slug' => 'duplicate']);
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test('App\\Filament\\Resources\\Posts\\Pages\\CreatePost')->fillForm([
            'title' => 'Artículo', 'slug' => $slug, 'content' => '<p>Contenido.</p>', 'status' => 'draft',
        ])->call('create')->assertHasFormErrors(['slug' => $rule]);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_published_posts_require_a_non_future_date_and_valid_state(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $component = Livewire::test('App\\Filament\\Resources\\Posts\\Pages\\CreatePost')->fillForm([
            'title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido.</p>', 'status' => 'published',
        ])->call('create')->assertHasFormErrors(['published_at']);
        $component->fillForm(['published_at' => now()->addDay()->format('Y-m-d H:i:s')])->call('create')->assertHasFormErrors(['published_at']);
        $component->fillForm(['status' => 'arbitrary', 'published_at' => null])->call('create')->assertHasFormErrors(['status']);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_legal_types_are_unique_and_revision_changes_only_with_content(): void
    {
        $this->freezeTime();
        $page = LegalPage::factory()->create();
        $date = $page->content_updated_at;
        $page->update(['is_published' => true]);
        $this->assertSame(1, $page->fresh()->revision);
        $this->assertTrue($page->fresh()->content_updated_at->equalTo($date));
        $this->travel(1)->day();
        $page->update(['content' => '<p>Contenido revisado.</p>']);
        $this->assertSame(2, $page->fresh()->revision);
        $this->assertTrue($page->fresh()->content_updated_at->greaterThan($date));
        LegalPage::factory()->create(['type' => 'privacy']);
        $this->assertSame([$page->id], LegalPage::published()->get()->modelKeys());
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test('App\\Filament\\Resources\\LegalPages\\Pages\\CreateLegalPage')->fillForm([
            'type' => 'terms', 'content' => '<p>Duplicado.</p>', 'is_published' => false,
        ])->call('create')->assertHasFormErrors(['type' => 'unique']);
        $this->assertDatabaseCount('legal_pages', 2);
    }
}
