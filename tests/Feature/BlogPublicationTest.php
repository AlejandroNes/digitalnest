<?php

namespace Tests\Feature;

use App\Filament\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategories\Pages\EditPostCategory;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Redirect;
use App\Models\SearchSetting;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BlogPublicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function administrator(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
    }

    #[TestWith([CreatePost::class, 'title'])]
    #[TestWith([CreatePostCategory::class, 'name'])]
    public function test_slug_follows_title_until_personalized(string $page, string $field): void
    {
        $this->administrator();
        Livewire::test($page)->set('data.'.$field, 'Primera noticia')->assertSet('data.slug', 'primera-noticia')
            ->set('data.'.$field, 'Segunda noticia')->assertSet('data.slug', 'segunda-noticia')
            ->set('data.slug', 'mi-url')->set('data.'.$field, 'Otro título')->assertSet('data.slug', 'mi-url');
    }

    public function test_editing_title_preserves_existing_url(): void
    {
        $this->administrator();
        $post = Post::factory()->published()->create(['slug' => 'url-estable']);

        Livewire::test(EditPost::class, ['record' => $post->slug])->set('data.title', 'Otro título')->call('save')->assertHasNoFormErrors();

        $this->assertSame('url-estable', $post->fresh()->slug);
        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_public_url_changes_redirect_old_aliases_without_exposing_unpublished_content(): void
    {
        $this->administrator();
        $post = Post::factory()->published()->create(['slug' => 'primera', 'title' => 'Contenido del artículo']);
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);

        $page->fillForm(['slug' => 'segunda'])->call('save')->assertHasNoFormErrors();
        $page->fillForm(['slug' => 'tercera'])->call('save')->assertHasNoFormErrors();

        $this->get('/blog/primera')->assertRedirect('/blog/tercera')->assertStatus(301);
        $this->get('/blog/segunda')->assertRedirect('/blog/tercera')->assertStatus(301);
        $this->get('/blog/tercera')->assertOk()->assertSee('Contenido del artículo');
        $page->fillForm(['status' => 'draft'])->call('save')->assertHasNoFormErrors();
        $this->get('/blog/tercera')->assertNotFound()->assertDontSee('Contenido del artículo');
        $this->get('/blog/primera')->assertRedirect('/blog/tercera');
    }

    public function test_changing_draft_slug_does_not_create_redirects(): void
    {
        $this->administrator();
        $post = Post::factory()->create(['slug' => 'borrador']);

        Livewire::test(EditPost::class, ['record' => $post->slug])->fillForm(['slug' => 'privado'])->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseCount('redirects', 0);
        $this->get('/blog/borrador')->assertNotFound();
        $this->get('/blog/privado')->assertNotFound();
    }

    public function test_returning_to_reserved_old_alias_is_rejected_without_creating_a_cycle(): void
    {
        $this->administrator();
        $post = Post::factory()->published()->create(['slug' => 'primera']);
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);
        $page->fillForm(['slug' => 'segunda'])->call('save')->assertHasNoFormErrors();

        $page->fillForm(['slug' => 'primera', 'title' => 'Cambios pendientes'])->call('save')->assertHasFormErrors(['slug'])
            ->assertSet('data.title', 'Cambios pendientes');

        $this->assertSame('segunda', $post->fresh()->slug);
        $this->get('/blog/primera')->assertRedirect('/blog/segunda');
        $page->fillForm(['slug' => 'tercera'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('tercera', $post->fresh()->slug);
    }

    public function test_existing_source_redirect_blocks_rename_and_rolls_back_categories(): void
    {
        $this->administrator();
        Redirect::factory()->create(['source' => '/blog/original', 'destination' => '/contacto']);
        $post = Post::factory()->published()->create(['slug' => 'original']);
        $old = PostCategory::factory()->create();
        $new = PostCategory::factory()->create();
        $post->categories()->attach($old);

        $page = Livewire::test(EditPost::class, ['record' => $post->slug])->fillForm(['slug' => 'nueva', 'categories' => [$new->id]])
            ->call('save')->assertHasFormErrors(['slug']);

        $this->assertSame('original', $post->fresh()->slug);
        $this->assertSame([$old->id], $post->fresh()->categories->modelKeys());
        $this->assertDatabaseHas('redirects', ['source' => '/blog/original', 'destination' => '/contacto']);
        $this->assertDatabaseCount('redirects', 1);
        Redirect::query()->firstOrFail()->delete();
        $page->fillForm(['slug' => 'nueva', 'categories' => [$new->id]])->call('save')->assertHasNoFormErrors();
        $this->assertSame('nueva', $post->fresh()->slug);
        $this->assertSame([$new->id], $post->fresh()->categories->modelKeys());
    }

    public function test_new_article_cannot_take_a_reserved_redirect_url(): void
    {
        $this->administrator();
        Redirect::factory()->create(['source' => '/blog/reservada', 'destination' => '/contacto']);

        Livewire::test(CreatePost::class)->fillForm(['title' => 'Artículo', 'slug' => 'reservada', 'content' => '<p>Contenido.</p>'])
            ->call('create')->assertHasFormErrors(['slug']);

        $this->assertDatabaseCount('posts', 0);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_stale_editor_cannot_overwrite_text_or_categories_even_with_same_timestamp(bool $categoriesOnly): void
    {
        $this->administrator();
        $this->freezeTime();
        $post = Post::factory()->create(['title' => 'Inicial']);
        $category = PostCategory::factory()->create();
        $first = Livewire::test(EditPost::class, ['record' => $post->slug]);
        $second = Livewire::test(EditPost::class, ['record' => $post->slug])->fillForm(['title' => 'Cambios sin guardar']);
        $first->fillForm($categoriesOnly ? ['categories' => [$category->id]] : ['title' => 'Guardado por otra sesión'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($post->getRawOriginal('updated_at'), $post->fresh()->getRawOriginal('updated_at'));

        $second->call('save')->assertHasFormErrors(['title'])->assertSee('Este contenido cambió')
            ->assertSet('data.title', 'Cambios sin guardar');

        $this->assertSame($categoriesOnly ? 'Inicial' : 'Guardado por otra sesión', $post->fresh()->title);
        $this->assertSame($categoriesOnly ? [$category->id] : [], $post->fresh()->categories->modelKeys());
    }

    public function test_revision_refreshes_after_saving_categories_and_allows_another_save(): void
    {
        $this->administrator();
        $post = Post::factory()->create();
        $category = PostCategory::factory()->create();
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);

        $page->fillForm(['categories' => [$category->id]])->call('save')->assertHasNoFormErrors()
            ->fillForm(['title' => 'Segundo guardado'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Segundo guardado', $post->fresh()->title);
        $this->assertSame([$category->id], $post->fresh()->categories->modelKeys());
    }

    public function test_stale_category_editor_cannot_overwrite_a_newer_name(): void
    {
        $this->administrator();
        $category = PostCategory::factory()->create();
        $page = Livewire::test(EditPostCategory::class, ['record' => $category->slug]);
        $category->update(['name' => 'Nombre guardado']);

        $page->fillForm(['name' => 'Nombre antiguo'])->call('save')->assertHasFormErrors(['name'])->assertSet('data.name', 'Nombre antiguo');

        $this->assertSame('Nombre guardado', $category->fresh()->name);
    }

    public function test_stale_editor_cannot_delete_a_record_changed_in_another_session(): void
    {
        $this->administrator();
        $post = Post::factory()->create();
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);
        $post->update(['title' => 'Nuevo contenido']);

        $page->callAction(DeleteAction::class)->assertHasErrors(['data.title']);

        $this->assertModelExists($post);
    }

    public function test_client_cannot_replace_the_original_revision(): void
    {
        $this->administrator();
        $post = Post::factory()->create();
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $page->set('editingRevision', 'revision-falsa');
    }

    public function test_blog_forms_render_native_unsaved_changes_warning(): void
    {
        $this->administrator();

        $this->get('/admin/posts/create')->assertOk()->assertSee('savedDataHash', false);
        $this->get('/admin/post-categories/create')->assertOk()->assertSee('savedDataHash', false);
    }

    public function test_sitemap_and_canonical_follow_new_url_and_withdrawal_removes_it(): void
    {
        $this->administrator();
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.url' => 'https://corporate.com']);
        SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
        $post = Post::factory()->published()->create(['slug' => 'anterior']);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/anterior', false);
        $this->app->instance('env', 'testing');
        $page = Livewire::test(EditPost::class, ['record' => $post->slug]);

        $page->fillForm(['slug' => 'actual'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('actual', $post->fresh()->slug);
        $this->app->instance('env', 'production');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/blog/actual', false)->assertDontSee('/blog/anterior', false);
        $this->get('/blog/actual')->assertOk()->assertSee('rel="canonical" href="https://corporate.com/blog/actual"', false);
        $this->app->instance('env', 'testing');
        $page->fillForm(['status' => 'draft'])->call('save')->assertHasNoFormErrors();
        $this->app->instance('env', 'production');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/blog/actual', false);
    }
}
