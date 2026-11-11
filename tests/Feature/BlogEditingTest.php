<?php

namespace Tests\Feature;

use App\Filament\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategories\Pages\EditPostCategory;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BlogEditingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function administrator(): User
    {
        $actor = User::factory()->administrator()->create();
        $this->actingAs($actor);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function article(): array
    {
        return ['title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido válido.</p>', 'status' => 'draft'];
    }

    #[TestWith(['<p><br></p>'])]
    #[TestWith(['<p>&nbsp; &#8203;</p>'])]
    #[TestWith(['<p><img src=x></p>'])]
    public function test_rejects_content_without_visible_text_and_preserves_form(string $html): void
    {
        $this->administrator();
        Livewire::test(CreatePost::class)->fillForm([...$this->article(), 'content' => $html])->call('create')
            ->assertHasFormErrors(['content'])->assertSee('Escribe contenido visible para el artículo.')
            ->assertSet('data.title', 'Artículo');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_relationship_failure_rolls_back_article_and_keeps_form_retryable(): void
    {
        $this->administrator();
        $category = PostCategory::factory()->create();
        $once = true;
        Post::created(function () use ($category, &$once): void {
            if ($once) {
                $once = false;
                $category->delete();
            }
        });

        $page = Livewire::test(CreatePost::class)->fillForm([...$this->article(), 'categories' => [$category->id]])
            ->call('create')->assertHasFormErrors(['categories'])->assertSet('data.title', 'Artículo');

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_category_post', 0);
        $this->assertModelExists($category);
        $page->call('create')->assertHasNoFormErrors();
        $this->assertSame([$category->id], Post::query()->sole()->categories->modelKeys());
    }

    public function test_duplicate_slug_race_is_a_validation_error_and_rolls_back_category_changes(): void
    {
        $this->administrator();
        $post = Post::factory()->create(['slug' => 'original']);
        $old = PostCategory::factory()->create();
        $new = PostCategory::factory()->create();
        $post->categories()->attach($old);
        $once = true;
        Post::updating(function () use (&$once): void {
            if ($once) {
                $once = false;
                Post::factory()->create(['slug' => 'nueva-url']);
            }
        });

        Livewire::test(EditPost::class, ['record' => $post->slug])
            ->fillForm(['slug' => 'nueva-url', 'categories' => [$new->id]])->call('save')
            ->assertHasFormErrors(['slug'])->assertSee('Esta URL ya está en uso.')
            ->assertSet('data.slug', 'nueva-url');

        $this->assertSame('original', $post->fresh()->slug);
        $this->assertSame([$old->id], $post->fresh()->categories->modelKeys());
        $this->assertDatabaseCount('posts', 1);
    }

    #[TestWith([CreatePost::class])]
    #[TestWith([CreatePostCategory::class])]
    public function test_creation_rechecks_inactive_actor_from_database(string $page): void
    {
        $actor = $this->administrator();
        $component = Livewire::test($page)->fillForm([...$this->article(), 'name' => 'Categoría']);
        User::query()->whereKey($actor->id)->update(['is_active' => false]);

        $component->call('create')->assertForbidden();

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_categories', 0);
    }

    #[TestWith([Post::class, EditPost::class])]
    #[TestWith([PostCategory::class, EditPostCategory::class])]
    public function test_edit_rechecks_revoked_access_without_discarding_saved_data(string $model, string $page): void
    {
        $actor = $this->administrator();
        $record = $model::factory()->create();
        $original = $record->fresh()->getAttributes();
        $component = Livewire::test($page, ['record' => $record->slug]);
        User::query()->whereKey($actor->id)->update(['role' => UserRole::User]);

        $component->call('save')->assertForbidden();

        $this->assertSame($original, $record->fresh()->getAttributes());
    }

    #[TestWith([Post::class, EditPost::class])]
    #[TestWith([PostCategory::class, EditPostCategory::class])]
    public function test_delete_rechecks_revoked_delete_permission(string $model, string $page): void
    {
        $actor = $this->administrator();
        $record = $model::factory()->create();
        $component = Livewire::test($page, ['record' => $record->slug]);
        User::query()->whereKey($actor->id)->update(['role' => UserRole::Editor]);

        $component->callAction(DeleteAction::class)->assertForbidden();

        $this->assertModelExists($record);
    }

    public function test_category_deleted_while_editing_is_rejected_without_writing(): void
    {
        $this->administrator();
        $category = PostCategory::factory()->create();
        $post = Post::factory()->create();
        $component = Livewire::test(EditPost::class, ['record' => $post->slug])->fillForm(['categories' => [$category->id], 'title' => 'Conservar borrador']);
        $category->delete();

        $component->call('save')->assertHasFormErrors()->assertSet('data.title', 'Conservar borrador');

        $this->assertSame($post->title, $post->fresh()->title);
        $this->assertDatabaseCount('post_category_post', 0);
    }

    public function test_original_image_deleted_after_opening_form_blocks_save_without_losing_text(): void
    {
        $this->administrator();
        Storage::fake('public');
        $media = MediaAsset::factory()->create()->addMedia(UploadedFile::fake()->image('portada.png'))->toMediaCollection('images');
        $post = Post::factory()->create(['image_id' => $media->id]);
        $page = Livewire::test(EditPost::class, ['record' => $post->slug])->fillForm(['title' => 'Conservar mis cambios']);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());

        $page->call('save')->assertHasFormErrors(['image_id'])->assertSet('data.title', 'Conservar mis cambios');

        $this->assertSame($post->title, $post->fresh()->title);
    }

    public function test_category_creation_slug_race_preserves_form_and_can_be_retried(): void
    {
        $this->administrator();
        $once = true;
        PostCategory::creating(function (PostCategory $category) use (&$once): void {
            if ($once) {
                $once = false;
                DB::table('post_categories')->insert($category->getAttributes());
            }
        });
        $page = Livewire::test(CreatePostCategory::class)->fillForm(['name' => 'Categoría de prueba', 'slug' => 'prueba']);

        $page->call('create')->assertHasFormErrors(['slug'])->assertSet('data.name', 'Categoría de prueba');

        $this->assertDatabaseCount('post_categories', 0);
        $page->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('post_categories', ['name' => 'Categoría de prueba', 'slug' => 'prueba']);
    }
}
