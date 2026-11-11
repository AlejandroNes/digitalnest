<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\SearchSettings;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Livewire\MediaLibraryBrowser;
use App\Mail\SmtpTestMessage;
use App\Models\GeneralSetting;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use App\Rules\MediaSelection;
use App\Services\ApplicationCache;
use App\Services\MediaUploader;
use App\UserRole;
use App\View\Components\ContentImage;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Once;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function image(string $name = 'equipo.png'): MediaAsset
    {
        return app(MediaUploader::class)->upload(auth()->user(), UploadedFile::fake()->image($name, $name === 'equipo.png' ? 120 : 121 + crc32($name) % 29, 80), 'Equipo', 'Nuestro equipo')['asset'];
    }

    public function test_selecting_only_changes_the_target_field_and_cancel_preserves_unsaved_data(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $action = TestAction::make('chooseImage')->schemaComponent('image_id');
        $page = Livewire::test(CreatePost::class)->fillForm(['title' => 'Sin guardar'])
            ->mountAction($action)->assertMountedActionModalSee('Seleccionar imagen');
        $page->fillForm(['selected_media_id' => $media->id])->call('unmountAction')
            ->assertSet('data.image_id', null)->assertSet('data.title', 'Sin guardar');
        $page->callAction($action, ['selected_media_id' => $media->id])->assertHasNoActionErrors()
            ->assertSet('data.image_id', $media->id)->assertSet('data.seo_image_id', null);
        $page->callAction(TestAction::make('chooseImage')->schemaComponent('seo_image_id'), ['selected_media_id' => $media->id])
            ->assertHasNoActionErrors()->assertSet('data.seo_image_id', $media->id);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_browser_selection_survives_filters_and_uses_media_id(): void
    {
        $asset = $this->image();
        $media = $asset->getFirstMedia('images');
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'parent:data.image_id'])
            ->callTableAction('selectImage', $asset)->assertSet('selectedMediaId', $media->id)
            ->assertDispatched('media-library-selection', target: 'parent:data.image_id', mediaId: $media->id)
            ->searchTable('Sin coincidencias')->assertSet('selectedMediaId', $media->id)
            ->assertCanNotSeeTableRecords([$asset]);
    }

    public function test_invalid_ids_missing_original_and_revoked_permission_are_rejected(): void
    {
        $asset = $this->image();
        $media = $asset->getFirstMedia('images');
        $action = TestAction::make('chooseImage')->schemaComponent('image_id');
        Livewire::test(CreatePost::class)->callAction($action, ['selected_media_id' => '../imagen.png'])->assertHasActionErrors(['selected_media_id']);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        Livewire::test(CreatePost::class)->callAction($action, ['selected_media_id' => $media->id])->assertHasActionErrors(['selected_media_id']);
        $browser = Livewire::test(MediaLibraryBrowser::class, ['target' => 'parent:data.image_id']);
        auth()->user()->update(['is_active' => false]);
        $browser->call('resetTableSearch')->assertForbidden();
        $this->assertTrue(Validator::make(['image' => $media->id], ['image' => new MediaSelection])->fails());
    }

    public function test_identity_reuses_original_png_and_prevents_deletion(): void
    {
        $asset = $this->image('logo.png');
        $media = $asset->getFirstMedia('images');
        GeneralSetting::factory()->create(['id' => 1, 'logo' => 'site-identity/legacy.png']);
        Livewire::test(GeneralSettings::class)->fillForm(['logo_media_id' => $media->id, 'favicon_media_id' => $media->id])
            ->call('save')->assertHasNoFormErrors();
        $settings = GeneralSetting::current();
        $this->assertSame($media->getUrl(), $settings->imageUrl('logo'));
        $this->assertSame($media->getUrl(), $settings->imageUrl('favicon'));
        $this->assertSame('site-identity/legacy.png', $settings->logo);
        $this->assertTrue($asset->isReferenced());
        $this->assertContains('Logos y favicon del sitio', $asset->newQuery()->withUsage()->find($asset->id)->usageLabels());
        $this->assertDatabaseCount('media', 1);
        Livewire::test(GeneralSettings::class)->fillForm(['logo_media_id' => null, 'favicon_media_id' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull(GeneralSetting::current()->imageUrl('logo'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_upload_from_unsaved_parent_uses_shared_ingestion_and_deduplicates(): void
    {
        $file = UploadedFile::fake()->image('Equipo Original.png', 160, 80);
        $asset = app(MediaUploader::class)->upload(auth()->user(), $file, 'Equipo', 'Equipo original')['asset'];
        $browser = Livewire::test(MediaLibraryBrowser::class, ['target' => 'parent:data.image_id']);
        $browser->mountAction(TestAction::make('uploadImages')->table())->fillForm(['images' => [$file]])
            ->assertSet('mountedActions.0.data.metadata.0.name', 'Equipo Original');
        $metadata = $browser->get('mountedActions.0.data.metadata');
        foreach ($metadata as &$item) {
            $item['alt_text'] = 'Equipo original';
        }
        unset($item);
        $browser->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('existing', $browser->get('mediaUploadResults')[0]['status']);
        $browser->callTableAction('selectImage', $asset)->assertSet('selectedMediaId', $asset->getFirstMedia('images')->id);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_identity_fk_and_model_guard_prevent_removing_referenced_files(): void
    {
        $asset = $this->image();
        $media = $asset->getFirstMedia('images');
        GeneralSetting::factory()->create(['id' => 1, 'logo_dark_media_id' => $media->id]);
        try {
            $asset->delete();
            $this->fail('Referenced asset must not be deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        try {
            $media->delete();
            $this->fail('The restrictive foreign key must reject direct deletion.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertSame($media->getUrl(), GeneralSetting::current()->imageUrl('logo_dark'));
    }

    public function test_other_collection_or_missing_owner_cannot_be_confirmed_or_saved(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $media->update(['collection_name' => 'private']);
        $action = TestAction::make('chooseImage')->schemaComponent('image_id');
        Livewire::test(CreatePost::class)->callAction($action, ['selected_media_id' => $media->id])->assertHasActionErrors(['selected_media_id']);
        $media->update(['collection_name' => 'images', 'model_id' => 9999]);
        $this->assertTrue(Validator::make(['image' => $media->id], ['image' => new MediaSelection])->fails());
    }

    public function test_repeater_fields_have_separate_targets_and_optional_clear_preserves_files(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $form = Livewire::test(MediaPickerFixture::class);
        $items = array_keys($form->get('data.items'));
        $field = 'items.'.$items[0].'.image_id';
        $form->callAction(TestAction::make('chooseImage')->schemaComponent($field), ['selected_media_id' => $media->id])
            ->assertHasNoActionErrors()->assertSet('data.'.$field, $media->id)
            ->assertSet('data.items.'.$items[1].'.image_id', null);
        $form->callAction(TestAction::make('clearImage')->schemaComponent($field))->assertSet('data.'.$field, null);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertDatabaseCount('media', 1);
    }

    public function test_identity_limits_apply_to_existing_images_and_mail_uses_the_shared_logo(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $original = Storage::disk('public')->get($media->getPathRelativeToRoot());
        Storage::disk('public')->put($media->getPathRelativeToRoot(), $original.str_repeat('a', 257 * 1024));
        Livewire::test(GeneralSettings::class)->fillForm(['favicon_media_id' => $media->id])->call('save')->assertHasFormErrors(['favicon_media_id']);
        Storage::disk('public')->put($media->getPathRelativeToRoot(), $original.str_repeat('a', 2049 * 1024));
        Livewire::test(GeneralSettings::class)->fillForm(['logo_media_id' => $media->id])->call('save')->assertHasFormErrors(['logo_media_id']);
        $valid = $this->image('otro.png')->getFirstMedia('images');
        $settings = GeneralSetting::factory()->create(['id' => 1, 'logo_media_id' => $valid->id]);
        $mail = new SmtpTestMessage($settings, ['address' => 'sender@example.test', 'name' => 'Empresa']);
        $mail->assertSeeInHtml($valid->getUrl(), false);
    }

    public function test_new_identity_metadata_is_cached_as_attributes_and_changes_are_visible(): void
    {
        $first = $this->image()->getFirstMedia('images');
        $second = $this->image('segunda.png')->getFirstMedia('images');
        $this->assertNotSame($first->id, $second->id);
        $settings = GeneralSetting::factory()->create(['id' => 1, 'logo_media_id' => $first->id]);
        $this->assertSame($first->getUrl(), GeneralSetting::current(false)->imageUrl('logo'));
        $settings->update(['logo_media_id' => $second->id]);
        $this->assertSame($second->getUrl(), GeneralSetting::current(false)->imageUrl('logo'));
        $cached = Cache::memo()->get(ApplicationCache::GENERAL);
        $this->assertIsArray($cached);
        $this->assertEquals($second->id, $cached['logo_media_id']);
        $this->assertArrayNotHasKey('logoMedia', $cached);
    }

    public function test_public_seo_uses_the_selected_variant_metadata_and_escapes_alt(): void
    {
        $asset = $this->image();
        $asset->update(['alt_text' => '<script>Equipo</script>']);
        $media = $asset->getFirstMedia('images');
        $post = Post::factory()->published()->create(['seo_image_id' => $media->id]);
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('property="og:image:width" content="120"', false)
            ->assertSee('property="og:image:type" content="image/webp"', false)
            ->assertSee('property="og:image:alt" content="&lt;script&gt;Equipo&lt;/script&gt;"', false);
        Storage::disk('public')->delete($media->getPathRelativeToRoot('optimized'));
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('property="og:image:type" content="image/png"', false)
            ->assertSee($media->getUrl(), false)->assertDontSee($media->getUrl('optimized'), false);
    }

    public function test_all_consumers_create_edit_and_reuse_the_same_media(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $files = Storage::disk('public')->allFiles();
        Livewire::test(CreatePost::class)->fillForm(['title' => 'Artículo', 'slug' => 'articulo', 'content' => '<p>Contenido</p>'])
            ->callAction(TestAction::make('chooseImage')->schemaComponent('image_id'), ['selected_media_id' => $media->id])
            ->callAction(TestAction::make('chooseImage')->schemaComponent('seo_image_id'), ['selected_media_id' => $media->id])
            ->call('create')->assertHasNoFormErrors();
        $post = Post::query()->firstOrFail();
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSet('data.image_id', $media->id)->assertSet('data.seo_image_id', $media->id)
            ->fillForm(['title' => 'Artículo actualizado'])->call('save')->assertHasNoFormErrors();
        Livewire::test(CreateService::class)
            ->fillForm(['name' => 'Servicio', 'slug' => 'servicio', 'description' => '<p>Descripción</p>'])
            ->callAction(TestAction::make('chooseImage')->schemaComponent('image_id'), ['selected_media_id' => $media->id])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateTestimonial::class)
            ->fillForm(['name' => 'Cliente', 'quote' => 'Excelente'])
            ->callAction(TestAction::make('chooseImage')->schemaComponent('image_id'), ['selected_media_id' => $media->id])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(SearchSettings::class)
            ->callAction(TestAction::make('chooseImage')->schemaComponent('og_image_id'), ['selected_media_id' => $media->id])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($media->id, $post->fresh()->image_id);
        $this->assertSame($media->id, Service::query()->firstOrFail()->image_id);
        $this->assertSame($media->id, Testimonial::query()->firstOrFail()->image_id);
        $this->assertSame($media->id, SearchSetting::current()->og_image_id);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 1);
    }

    public function test_revocation_after_opening_modal_blocks_confirmation_and_consumer_save(): void
    {
        $media = $this->image()->getFirstMedia('images');
        $action = TestAction::make('chooseImage')->schemaComponent('image_id');
        $page = Livewire::test(CreatePost::class)->mountAction($action)->fillForm(['selected_media_id' => $media->id]);
        User::query()->whereKey(auth()->id())->update(['is_active' => false]);
        $page->callMountedAction()->assertSet('data.image_id', null);
        $this->assertDatabaseCount('posts', 0);
        $this->assertTrue(Validator::make(['image' => $media->id], ['image' => new MediaSelection])->fails());
    }

    public function test_stored_dimensions_support_nonlocal_rendering_without_decoding_the_original(): void
    {
        $media = $this->image()->getFirstMedia('images');
        config(['filesystems.disks.public.driver' => 's3']);
        $component = new ContentImage($media);
        $this->assertSame(120, $component->width);
        $this->assertSame(80, $component->height);
        $this->assertSame($media->getUrl('optimized'), $component->src);
    }

    public function test_pending_identity_migration_keeps_legacy_identity_and_settings_editable(): void
    {
        $migration = require database_path('migrations/2026_10_09_224627_add_media_references_to_general_settings_table.php');
        $migration->down();
        Once::instance()->flush();
        try {
            GeneralSetting::factory()->create(['id' => 1, 'company_name' => 'Anterior', 'logo' => 'site-identity/legacy.png']);
            Livewire::test(GeneralSettings::class)->fillForm(['company_name' => 'Actualizada'])->call('save')->assertHasNoFormErrors();
            $this->assertSame('Actualizada', GeneralSetting::current()->company_name);
            $this->assertSame(Storage::disk('public')->url('site-identity/legacy.png'), GeneralSetting::current()->imageUrl('logo'));
            $this->assertFalse(GeneralSetting::hasMediaReferences());
        } finally {
            $migration->up();
            Once::instance()->flush();
        }
        $this->assertTrue(GeneralSetting::hasMediaReferences());
    }

    public function test_favicon_rejects_jpeg_and_legacy_paths_remain_supported(): void
    {
        $media = $this->image('photo.jpg')->getFirstMedia('images');
        Livewire::test(GeneralSettings::class)->fillForm(['favicon_media_id' => $media->id])->call('save')->assertHasFormErrors(['favicon_media_id']);
        GeneralSetting::factory()->create(['id' => 1, 'logo' => 'site-identity/legacy.png']);
        $this->assertSame(Storage::disk('public')->url('site-identity/legacy.png'), GeneralSetting::current()->imageUrl('logo'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor, 'is_active' => false]));
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'arbitrary'])->assertForbidden();
    }
}

class MediaPickerFixture extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['items' => [['image_id' => null], ['image_id' => null]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('items')->schema([
                MediaPicker::make('image_id'),
            ]),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}<x-filament-actions::modals /></div>';
    }
}
