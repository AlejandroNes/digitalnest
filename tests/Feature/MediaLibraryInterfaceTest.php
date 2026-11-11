<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\MediaUploader;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MediaLibraryInterfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_grid_uses_thumbnails_and_paginates_with_escaped_titles(): void
    {
        Storage::fake('public');
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);
        MediaAsset::factory()->count(25)->create();
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('panorama.png', 800, 100), '<script>alert(1)</script>', 'Panorama')['asset'];
        $media = $asset->getFirstMedia('images');
        $page = Livewire::test(ListMediaAssets::class)->assertOk()->assertCanSeeTableRecords([$asset]);

        $page->assertSee($media->getUrl('thumb'), false)->assertDontSee($media->getUrl(), false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertCount(24, $page->instance()->getTableRecords());
        $page->call('gotoPage', 2)->assertCanNotSeeTableRecords([$asset]);
        $this->assertCount(2, $page->instance()->getTableRecords());
    }

    #[TestWith(['Título único'])]
    #[TestWith(['Descripción singular'])]
    #[TestWith(['foto-original-equipo.png'])]
    #[TestWith(['Foto Original Equipo.png'])]
    public function test_search_matches_title_alt_original_name_and_stored_name(string $search): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('Foto Original Equipo.png'), 'Título único', 'Descripción singular')['asset'];
        $other = MediaAsset::factory()->create(['name' => 'Otra', 'alt_text' => 'Otro paisaje']);

        Livewire::test(ListMediaAssets::class)->searchTable($search)->assertCanSeeTableRecords([$asset])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_combined_filters_sort_and_reset_show_the_correct_records(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        $old = MediaAsset::factory()->create(['name' => 'Antigua', 'created_at' => '2025-01-01']);
        $old->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('images');
        $used = MediaAsset::factory()->create(['name' => 'Actual', 'created_at' => '2026-10-09']);
        $media = $used->addMedia(UploadedFile::fake()->image('new.png'))->toMediaCollection('images');
        Post::factory()->create(['seo_image_id' => $media->id]);
        $free = MediaAsset::factory()->create(['name' => 'Libre', 'created_at' => '2026-10-09']);
        $free->addMedia(UploadedFile::fake()->image('free.png'))->toMediaCollection('images');

        $page = Livewire::test(ListMediaAssets::class)->filterTable('format', 'image/png')
            ->filterTable('uploaded', ['from' => '2026-01-01', 'until' => '2026-12-31'])
            ->filterTable('usage', 'used')->assertCanSeeTableRecords([$used])->assertCanNotSeeTableRecords([$old, $free]);
        $page->filterTable('usage', 'unused')->assertCanSeeTableRecords([$free])->assertCanNotSeeTableRecords([$used]);
        $page->resetTableFilters()->sortTable('name')->assertCanSeeTableRecords([$used, $old, $free], inOrder: true);
        $page->sortTable('created_at', 'desc')->assertCanSeeTableRecords([$free, $used, $old], inOrder: true);
        $page->searchTable('No existe')->callAction(TestAction::make('clearFilters')->table())
            ->assertCanSeeTableRecords([$old, $used, $free]);
    }

    public function test_preview_shows_real_metadata_and_usage_without_exposing_private_paths_or_settings_links(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('equipo.png', 600, 400), 'Equipo', 'Personas')['asset'];
        $media = $asset->getFirstMedia('images');
        Service::factory()->create(['image_id' => $media->id]);
        SearchSetting::factory()->create(['og_image_id' => $media->id]);

        Livewire::test(ListMediaAssets::class)->mountAction(TestAction::make('preview')->table($asset))
            ->assertActionMounted(TestAction::make('preview')->table($asset))
            ->assertMountedActionModalSee('600 × 400')->assertMountedActionModalSee('Servicios e imágenes SEO')->assertMountedActionModalSee('Imagen social del sitio')
            ->assertMountedActionModalSee('URL del original')->assertMountedActionModalDontSee('/admin/search-settings', false)
            ->assertMountedActionModalDontSee(Storage::disk('public')->path(''), false);
    }

    public function test_missing_thumbnail_never_loads_original_in_grid_and_missing_original_has_no_link(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('photo.png'), 'Foto', 'ALT')['asset'];
        $media = $asset->getFirstMedia('images');
        Storage::disk('public')->delete($media->getPathRelativeToRoot('thumb'));
        Livewire::test(ListMediaAssets::class)->assertSee('Miniatura no disponible')->assertDontSee($media->getUrl(), false);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        Livewire::test(ListMediaAssets::class)->mountAction(TestAction::make('preview')->table($asset))
            ->assertActionMounted(TestAction::make('preview')->table($asset))
            ->assertMountedActionModalSee('El archivo original no está disponible.')->assertMountedActionModalDontSee('Abrir original en otra pestaña');
    }

    public function test_metadata_modal_saves_without_renaming_or_replacing_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $asset = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('photo.png'), 'Foto', 'ALT')['asset'];
        $files = Storage::disk('public')->allFiles();
        $hash = $asset->content_hash;

        Livewire::test(ListMediaAssets::class)->callAction(TestAction::make('editMetadata')->table($asset), ['name' => 'Revisada', 'alt_text' => 'Descripción revisada'])
            ->assertHasNoFormErrors()->assertNotified();
        $this->assertSame('Revisada', $asset->fresh()->name);
        $this->assertSame('Descripción revisada', $asset->fresh()->alt_text);
        $this->assertSame($hash, $asset->fresh()->content_hash);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_admin_can_delete_unused_image_but_editor_cannot_invoke_the_action(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $asset = app(MediaUploader::class)->upload($admin, UploadedFile::fake()->image('free.png'), 'Libre', 'ALT')['asset'];
        $this->actingAs(User::factory()->create());
        Livewire::test(ListMediaAssets::class)->assertActionHidden(TestAction::make('deleteImage')->table($asset))
            ->call('mountAction', 'deleteImage', [], ['table' => true, 'recordKey' => (string) $asset->id])->call('callMountedAction');
        $this->assertModelExists($asset);
        $this->actingAs($admin);
        Livewire::test(ListMediaAssets::class)->callAction(TestAction::make('deleteImage')->table($asset))->assertNotified();
        $this->assertModelMissing($asset);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_reference_added_after_delete_confirmation_prevents_deletion(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $asset = app(MediaUploader::class)->upload($admin, UploadedFile::fake()->image('used.png'), 'Usada', 'ALT')['asset'];
        $media = $asset->getFirstMedia('images');
        $page = Livewire::test(ListMediaAssets::class)->mountAction(TestAction::make('deleteImage')->table($asset));
        Testimonial::factory()->create(['image_id' => $media->id]);
        $page->callMountedAction();

        $this->assertModelExists($asset);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_rendering_48_cards_does_not_add_a_query_per_card(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        MediaAsset::factory()->count(48)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        Livewire::test(ListMediaAssets::class);
        $first = count(DB::getQueryLog());
        DB::flushQueryLog();
        $page = Livewire::test(ListMediaAssets::class)->set('tableRecordsPerPage', 48);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(48, $page->instance()->getTableRecords());
        $this->assertLessThanOrEqual($first * 2 + 4, count($queries));
        $this->assertLessThan(20, count(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'media_assets'))));
    }

    public function test_multiple_upload_form_prepares_metadata_and_saves_through_shared_uploader(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')
            ->fillForm(['images' => [UploadedFile::fake()->image('Equipo.png', 40, 20), UploadedFile::fake()->image('Oficina.png', 20, 40)]]);
        $metadata = array_values($page->get('mountedActions.0.data.metadata') ?? []);
        $this->assertCount(2, $metadata);
        foreach ($metadata as &$item) {
            $item['alt_text'] = 'Descripción de '.$item['name'];
        }
        unset($item);
        $page->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('media', 2);
        $this->assertSame(['created', 'created'], array_column($page->get('mediaUploadResults'), 'status'));
        $this->assertCount(6, Storage::disk('public')->allFiles());
        $page->assertSee('Resultado de la carga')->assertSee('Equipo.png')->assertSee('Guardada');
    }

    public function test_ten_file_batch_reports_duplicates_and_partial_failures_and_retry_reuses_successes(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $files = [];
        for ($index = 0; $index < 8; $index++) {
            $files[] = UploadedFile::fake()->image('foto-'.$index.'.png', 20 + $index, 20);
        }
        $files[] = UploadedFile::fake()->createWithContent('duplicate.png', file_get_contents($files[0]->getRealPath()));
        $files[] = UploadedFile::fake()->image('too-wide.png', 6001, 1);
        foreach ([1, 2] as $attempt) {
            $page = Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')->fillForm(['images' => $files]);
            $metadata = array_values($page->get('mountedActions.0.data.metadata'));
            foreach ($metadata as &$item) {
                $item['alt_text'] = 'Descripción de la imagen';
            }
            unset($item);
            $page->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasNoFormErrors()->assertNotified();
            $results = $page->get('mediaUploadResults');
            $this->assertSame('existing', $results[8]['status']);
            $this->assertSame('failed', $results[9]['status']);
            $this->assertNotEmpty($results[9]['errors']);
            $page->assertSee('Ya estaba en la biblioteca')->assertSee('No guardada');
        }
        $this->assertDatabaseCount('media_assets', 8);
        $this->assertDatabaseCount('media', 8);
        $this->assertCount(24, Storage::disk('public')->allFiles());
    }

    public function test_upload_form_requires_alt_and_rejects_metadata_for_a_different_file(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')
            ->fillForm(['images' => [UploadedFile::fake()->image('equipo.png')]])->callMountedAction()->assertHasFormErrors();
        $metadata = array_values($page->get('mountedActions.0.data.metadata'));
        $metadata[0]['alt_text'] = 'Personas';
        $metadata[0]['upload_key'] = 'unrelated.png';
        $page->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasFormErrors(['images']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_batch_budget_is_shown_as_a_form_error_before_any_file_is_saved(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        config(['media-library.upload_limits.batch_kb' => 0]);
        $page = Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')
            ->fillForm(['images' => [UploadedFile::fake()->image('equipo.png')]]);
        $metadata = array_values($page->get('mountedActions.0.data.metadata'));
        $metadata[0]['alt_text'] = 'Equipo';
        $page->fillForm(['metadata' => $metadata])->callMountedAction()->assertHasFormErrors(['images']);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_cancelled_upload_and_forged_stored_paths_never_create_an_asset(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')
            ->fillForm(['images' => [UploadedFile::fake()->image('equipo.png')]])->unmountAction();
        Livewire::test(ListMediaAssets::class)->mountAction('uploadImages')
            ->fillForm(['images' => ['unrelated.png']])->callMountedAction()->assertHasFormErrors(['images']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_permission_revoked_after_opening_metadata_modal_cannot_save(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $asset = MediaAsset::factory()->create(['name' => 'Original']);
        $page = Livewire::test(ListMediaAssets::class)->mountAction(TestAction::make('editMetadata')->table($asset));
        User::query()->whereKey($user->id)->update(['is_active' => false]);
        $page->fillForm(['name' => 'No autorizada', 'alt_text' => 'ALT'])->callMountedAction()->assertForbidden();
        $this->assertSame('Original', $asset->fresh()->name);
    }

    public function test_metadata_preview_escapes_alt_filename_and_titles(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $asset = MediaAsset::factory()->create(['name' => '<img src=x onerror=alert(1)>', 'alt_text' => '<script>alert(2)</script>']);
        $media = $asset->addMedia(UploadedFile::fake()->image('legacy.png'))->toMediaCollection('images');
        $media->setCustomProperty('original_filename', '<script>alert(3)</script>.png')->save();
        Livewire::test(ListMediaAssets::class)->mountAction(TestAction::make('preview')->table($asset))
            ->assertMountedActionModalSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
            ->assertMountedActionModalDontSee('<script>alert(2)</script>', false)
            ->assertMountedActionModalDontSee('<script>alert(3)</script>', false)
            ->assertMountedActionModalDontSee('<img src=x onerror=alert(1)>', false);
    }

    #[TestWith([Post::class, 'image_id'])]
    #[TestWith([Post::class, 'seo_image_id'])]
    #[TestWith([Service::class, 'image_id'])]
    #[TestWith([Service::class, 'seo_image_id'])]
    #[TestWith([Testimonial::class, 'image_id'])]
    #[TestWith([SearchSetting::class, 'og_image_id'])]
    public function test_each_consumer_is_detected_by_usage_filter_and_disables_delete(string $model, string $column): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('used.png'))->toMediaCollection('images');
        $model::factory()->create([$column => $media->id]);
        $free = MediaAsset::factory()->create();

        Livewire::test(ListMediaAssets::class)->filterTable('usage', 'used')->assertCanSeeTableRecords([$asset])
            ->assertCanNotSeeTableRecords([$free])->assertActionDisabled(TestAction::make('deleteImage')->table($asset));
        $this->assertTrue($asset->isReferenced());
    }

    #[TestWith(['jpeg', 'image/jpeg'])]
    #[TestWith(['png', 'image/png'])]
    #[TestWith(['webp', 'image/webp'])]
    public function test_format_filter_uses_original_mime_and_not_the_webp_thumbnail(string $extension, string $mime): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('format.'.$extension))->toMediaCollection('images');
        $other = MediaAsset::factory()->create();

        Livewire::test(ListMediaAssets::class)->filterTable('format', $mime)->assertCanSeeTableRecords([$asset])->assertCanNotSeeTableRecords([$other]);
        $this->assertSame($mime, $media->mime_type);
        $this->assertTrue($media->hasGeneratedConversion('thumb'));
    }
}
