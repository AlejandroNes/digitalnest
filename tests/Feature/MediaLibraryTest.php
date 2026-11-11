<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_images_can_be_uploaded_searched_and_have_alt_and_webp_derivatives(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateMediaAsset::class)->fillForm([
            'name' => 'Identidad corporativa', 'alt_text' => 'Logo de la empresa',
            'image' => UploadedFile::fake()->image('logo.png', 1800, 1000),
        ])->call('create')->assertHasNoFormErrors();
        $asset = MediaAsset::query()->firstOrFail();
        $media = $asset->getFirstMedia('images');
        $this->assertSame('Logo de la empresa', $asset->alt_text);
        $this->assertSame('public', $media->disk);
        $this->assertTrue($media->hasGeneratedConversion('optimized'));
        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $path = $media->getPathRelativeToRoot('optimized');
        Storage::disk('public')->assertExists($path);
        $size = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame('image/webp', $size['mime']);
        $this->assertLessThanOrEqual(1600, $size[0]);
        $this->assertSame($media->getUrl('optimized'), $asset->imageUrl());
        Livewire::test(ListMediaAssets::class)->searchTable('Identidad')->assertCanSeeTableRecords([$asset]);
        Livewire::test(ListMediaAssets::class)->searchTable('Ausente')->assertCanNotSeeTableRecords([$asset]);
        Livewire::test(EditMediaAsset::class, ['record' => $asset->id])->fillForm(['alt_text' => 'Descripción revisada'])
            ->set('data.image', [])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Descripción revisada', $asset->fresh()->alt_text);
        $this->assertDatabaseCount('media', 1);
    }

    #[TestWith(['payload.svg', 'image/svg+xml'])]
    #[TestWith(['payload.php', 'application/x-httpd-php'])]
    #[TestWith(['fake.png', 'text/plain'])]
    public function test_invalid_images_are_not_stored(string $name, string $mime): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(CreateMediaAsset::class)->fillForm([
            'name' => 'Inválida', 'alt_text' => 'Imagen inválida', 'image' => UploadedFile::fake()->create($name, 10, $mime),
        ])->call('create')->assertHasFormErrors(['image']);
        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversized_images_and_missing_alt_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(CreateMediaAsset::class)->fillForm([
            'name' => 'Grande', 'alt_text' => '', 'image' => UploadedFile::fake()->image('large.png')->size(5121),
        ])->call('create')->assertHasFormErrors(['image', 'alt_text']);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_an_image_is_reused_without_copying_and_cannot_be_deleted_until_references_are_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('shared.png'))->toMediaCollection('images');
        $post = Post::factory()->create(['image_id' => $media->id]);
        $service = Service::factory()->create(['image_id' => $media->id]);
        $testimonial = Testimonial::factory()->create(['image_id' => $media->id]);
        $this->assertSame($post->imageUrl(), $service->imageUrl());
        $this->assertSame($asset->alt_text, $testimonial->imageAlt());
        $this->assertDatabaseCount('media', 1);
        $this->assertFalse(Gate::allows('delete', $asset));
        try {
            $asset->delete();
            $this->fail('La imagen referenciada no debe eliminarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        try {
            $media->delete();
            $this->fail('La restricción de base de datos debe proteger también al medio.');
        } catch (QueryException $exception) {
            $this->assertModelExists($media);
        }
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $post->delete();
        $service->delete();
        $testimonial->delete();
        $this->assertTrue(Gate::allows('delete', $asset));
        Livewire::test(EditMediaAsset::class, ['record' => $asset->id])->callAction(DeleteAction::class);
        $this->assertModelMissing($asset);
        $this->assertModelMissing($media);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_existing_library_image_can_be_selected_in_content_form(): void
    {
        Storage::fake('public');
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('shared.png'))->toMediaCollection('images');
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateService::class)->fillForm([
            'name' => 'Servicio', 'slug' => 'servicio', 'description' => '<p>Detalle.</p>', 'image_id' => $media->id,
            'sort_order' => 1, 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $this->assertSame($media->id, Service::query()->firstOrFail()->image_id);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_nonexistent_media_ids_and_forged_file_paths_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(CreateService::class)->fillForm([
            'name' => 'Servicio', 'slug' => 'servicio', 'description' => '<p>Detalle.</p>', 'image_id' => 999,
        ])->call('create')->assertHasFormErrors(['image_id']);
        Livewire::test(CreateMediaAsset::class)->fillForm([
            'name' => 'Falsificada', 'alt_text' => 'Texto', 'image' => ['unrelated.png'],
        ])->call('create')->assertHasFormErrors(['image']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('services', 0);
    }

    public function test_missing_files_return_null_and_original_is_used_if_derivative_is_missing(): void
    {
        Storage::fake('public');
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('image.png'))->toMediaCollection('images');
        Storage::disk('public')->delete($media->getPathRelativeToRoot('optimized'));
        $this->assertSame($media->getUrl(), $asset->imageUrl());
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $this->assertNull($asset->imageUrl());
        $this->assertNull(Service::factory()->create()->imageUrl());
    }

    public function test_media_policies_and_direct_urls_reject_guests_and_inactive_accounts(): void
    {
        $asset = MediaAsset::factory()->create();
        $editor = User::factory()->create();
        $inactive = User::factory()->administrator()->inactive()->create();
        $this->get('/admin/media-assets')->assertRedirect('/admin/login');
        $this->get('/admin/media-assets/create')->assertRedirect('/admin/login');
        $this->get('/admin/media-assets/'.$asset->id.'/edit')->assertRedirect('/admin/login');
        $this->actingAs($inactive)->get('/admin/media-assets')->assertForbidden();
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue(Gate::forUser($editor)->allows($ability, MediaAsset::class));
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, MediaAsset::class));
        }
        foreach (['view', 'update'] as $ability) {
            $this->assertTrue(Gate::forUser($editor)->allows($ability, $asset));
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, $asset));
        }
        $this->assertFalse(Gate::forUser($editor)->allows('delete', $asset));
    }
}
