<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactAssistantSettings;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\SearchSettings;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Livewire\MediaLibraryBrowser;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaUploader;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MediaModuleReliabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_correcting_selection_removes_the_previous_error_and_returns_the_media_id(): void
    {
        $this->authenticateAdministrator();
        $missing = MediaAsset::factory()->create();
        $valid = $this->image();
        $mediaId = $valid->getFirstMedia('images')->id;
        $this->assertNotSame($valid->id, $mediaId);

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'post:image'])
            ->call('selectMedia', $missing->id)->assertHasErrors(['image'])
            ->call('selectMedia', $valid->id)->assertHasNoErrors(['image'])
            ->assertSet('selectedMediaId', $mediaId)
            ->assertDispatched('media-library-selection', target: 'post:image', mediaId: $mediaId);

        $this->assertDatabaseCount('media', 1);
    }

    public function test_favicon_cards_explain_the_format_restriction_and_direct_selection_is_revalidated(): void
    {
        $this->authenticateAdministrator();
        $jpeg = $this->image('photo.jpg');

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'favicon', 'context' => 'favicon'])
            ->assertSee('Este campo requiere una imagen PNG.')
            ->assertActionDisabled(TestAction::make('selectImage')->table($jpeg))
            ->call('selectMedia', $jpeg->id)->assertHasErrors(['image'])->assertSet('selectedMediaId', null);

        $this->assertDatabaseCount('media', 1);
    }

    public function test_missing_original_is_visible_before_selection_and_cannot_be_selected(): void
    {
        $this->authenticateAdministrator();
        $asset = $this->image();
        $media = $asset->getFirstMedia('images');
        Storage::disk('public')->delete($media->getPathRelativeToRoot());

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'image'])
            ->assertSee('El archivo original no está disponible.')
            ->assertActionDisabled(TestAction::make('selectImage')->table($asset))
            ->call('selectMedia', $asset->id)->assertHasErrors(['image'])->assertSet('selectedMediaId', null);
    }

    public function test_clearing_single_selection_only_clears_the_temporary_choice(): void
    {
        $this->authenticateAdministrator();
        $media = $this->image()->getFirstMedia('images');

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'image', 'initialMediaId' => $media->id])
            ->assertSee('Quitar selección')->call('clearSelection')->assertSet('selectedMediaId', null)
            ->assertDispatched('media-library-selection', target: 'image', mediaId: null);

        $this->assertDatabaseCount('media', 1);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_clearing_multiple_selection_preserves_the_images_and_next_selection_starts_a_new_order(): void
    {
        $this->authenticateAdministrator();
        $first = $this->image('first.png', 120);
        $second = $this->image('second.png', 121);
        $browser = Livewire::test(MediaLibraryBrowser::class, ['target' => 'gallery', 'context' => 'gallery', 'multiple' => true])
            ->call('selectMedia', $first->id)->call('selectMedia', $second->id)->assertSee('Vaciar selección');

        $browser->call('clearSelection')->assertSet('selectedMediaIds', [])
            ->assertDispatched('media-library-selection', target: 'gallery', mediaIds: [])
            ->call('selectMedia', $second->id)->assertSet('selectedMediaIds', [$second->getFirstMedia('images')->id]);

        $this->assertDatabaseCount('media', 2);
    }

    public function test_upload_result_can_be_selected_without_clearing_search_or_filters(): void
    {
        $this->authenticateAdministrator();
        $browser = Livewire::test(MediaLibraryBrowser::class, ['target' => 'service:image'])
            ->searchTable('Filtro conservado')->filterTable('format', 'image/jpeg')
            ->mountAction(TestAction::make('uploadImages')->table())
            ->fillForm(['images' => [UploadedFile::fake()->image('new.png', 120, 80)]]);
        $metadata = array_values($browser->get('mountedActions.0.data.metadata'));
        $metadata[0]['alt_text'] = 'Una imagen nueva';
        $browser->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasNoActionErrors();
        $asset = MediaAsset::query()->sole();
        $this->assertSame($asset->id, $browser->get('mediaUploadResults')[0]['asset_id']);

        $browser->assertSee('Seleccionar new.png')->call('selectMedia', $asset->id)
            ->assertSet('selectedMediaId', $asset->getFirstMedia('images')->id)
            ->assertSet('tableSearch', 'Filtro conservado');

        $this->assertDatabaseCount('media', 1);
    }

    public function test_missing_selected_thumbnail_does_not_request_a_missing_url_or_load_the_original(): void
    {
        $this->authenticateAdministrator();
        $media = $this->image()->getFirstMedia('images');
        Storage::disk('public')->delete($media->getPathRelativeToRoot('thumb'));

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'image', 'initialMediaId' => $media->id])
            ->assertSee('Miniatura no disponible')->assertDontSee($media->getUrl('thumb'), false)->assertDontSee($media->getUrl(), false);

        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    #[TestWith(['selectMedia'])]
    #[TestWith(['clearSelection'])]
    public function test_selection_controls_recheck_revoked_access(string $method): void
    {
        $this->authenticateAdministrator();
        $asset = $this->image();
        $browser = Livewire::test(MediaLibraryBrowser::class, ['target' => 'image']);
        User::query()->whereKey(auth()->id())->update(['is_active' => false]);

        $browser->call($method, ...($method === 'selectMedia' ? [$asset->id] : []))->assertForbidden();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_temporary_uploads_cannot_target_a_non_upload_property(): void
    {
        $this->authenticateAdministrator();

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'image'])
            ->call('_startUpload', 'selectedMediaId', [['name' => 'forged.png', 'size' => 1024, 'type' => 'image/png']], false)->assertForbidden();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_mounted_upload_field_still_accepts_a_legitimate_temporary_upload(): void
    {
        $this->authenticateAdministrator();

        Livewire::test(MediaLibraryBrowser::class, ['target' => 'image'])
            ->mountAction(TestAction::make('uploadImages')->table())
            ->call('_startUpload', 'mountedActions.0.data.images', [['name' => 'photo.png', 'size' => 1024, 'type' => 'image/png']], true)
            ->assertDispatched('upload:generatedSignedUrl', name: 'mountedActions.0.data.images');

        $this->assertDatabaseCount('media', 0);
    }

    #[TestWith([GeneralSettings::class, 'logo_media_id'])]
    #[TestWith([GeneralSettings::class, 'logo_dark_media_id'])]
    #[TestWith([GeneralSettings::class, 'favicon_media_id'])]
    #[TestWith([SearchSettings::class, 'og_image_id'])]
    #[TestWith([ContactAssistantSettings::class, 'avatar_media_id'])]
    #[TestWith([CreatePost::class, 'image_id'])]
    #[TestWith([CreatePost::class, 'seo_image_id'])]
    #[TestWith([CreateService::class, 'image_id'])]
    #[TestWith([CreateTestimonial::class, 'image_id'])]
    #[TestWith([CreateProject::class, 'image_id'])]
    public function test_each_single_picker_can_select_then_remove_without_saving_its_parent(string $page, string $field): void
    {
        $this->authenticateAdministrator();
        $media = $this->image()->getFirstMedia('images');

        $component = Livewire::test($page)->callAction(TestAction::make('chooseImage')->schemaComponent($field), ['selected_media_id' => $media->id])
            ->assertHasNoActionErrors()->assertSet('data.'.$field, $media->id);
        if ($page === CreateProject::class) {
            $component->assertActionHidden(TestAction::make('clearImage')->schemaComponent($field));
        } else {
            $component->callAction(TestAction::make('clearImage')->schemaComponent($field))->assertSet('data.'.$field, null);
        }

        $this->assertDatabaseCount('media', 1);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    private function authenticateAdministrator(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function image(string $filename = 'photo.png', int $width = 120): MediaAsset
    {
        return app(MediaUploader::class)->upload(auth()->user(), UploadedFile::fake()->image($filename, $width, 80), 'Foto', 'Imagen de prueba')['asset'];
    }
}
