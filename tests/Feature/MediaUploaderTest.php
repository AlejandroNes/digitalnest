<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaUploader;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Tests\TestCase;

class MediaUploaderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upload_preserves_original_and_stores_legible_filename_and_dimensions(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $source = UploadedFile::fake()->image('source.png', 1800, 1000);
        $file = new UploadedFile($source->getRealPath(), 'Foto Equipo Ávila 2026.PNG', null, null, true);
        $original = file_get_contents($file->getRealPath());
        $result = app(MediaUploader::class)->upload($user, $file, 'Equipo', 'Personas del equipo');
        $media = $result['asset']->getFirstMedia('images');
        $this->assertTrue($result['created']);
        $this->assertSame('foto-equipo-avila-2026.png', $media->file_name);
        $this->assertSame('Foto Equipo Ávila 2026.PNG', $media->getCustomProperty('original_filename'));
        $this->assertSame(['width' => 1800, 'height' => 1000, 'mime' => 'image/png'], $media->getCustomProperty('dimensions.original'));
        $this->assertSame(1600, $media->getCustomProperty('dimensions.optimized.width'));
        $this->assertSame(320, $media->getCustomProperty('dimensions.thumb.width'));
        $this->assertSame($original, Storage::disk('public')->get($media->getPathRelativeToRoot()));
        $this->assertFileExists($file->getRealPath());
        $this->assertSame(hash('sha256', $original), $result['asset']->content_hash);
    }

    public function test_identical_bytes_reuse_the_first_asset_without_overwriting_metadata(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('first.png');
        $uploader = app(MediaUploader::class);
        $first = $uploader->upload($user, $file, 'Original', 'ALT original');
        $files = Storage::disk('public')->allFiles();
        $second = $uploader->upload($user, $file, 'Otro título', 'Otro ALT');
        $this->assertFalse($second['created']);
        $this->assertTrue($first['asset']->is($second['asset']));
        $this->assertSame('Original', $second['asset']->name);
        $this->assertSame('ALT original', $second['asset']->alt_text);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_different_content_with_the_same_filename_has_independent_paths(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $uploader = app(MediaUploader::class);
        $first = $uploader->upload($user, UploadedFile::fake()->image('same.png', 10, 10), 'Uno', 'Uno')['asset']->getFirstMedia('images');
        $second = $uploader->upload($user, UploadedFile::fake()->image('same.png', 20, 10), 'Dos', 'Dos')['asset']->getFirstMedia('images');
        $this->assertSame('same.png', $first->file_name);
        $this->assertSame('same.png', $second->file_name);
        $this->assertNotSame($first->getPathRelativeToRoot(), $second->getPathRelativeToRoot());
        Storage::disk('public')->assertExists([$first->getPathRelativeToRoot(), $second->getPathRelativeToRoot()]);
    }

    #[TestWith(['payload.php.png'])]
    #[TestWith(['photo..png'])]
    #[TestWith(['fake.jpg'])]
    #[TestWith(["bad\x01.png"])]
    public function test_unsafe_names_and_extension_mismatches_are_rejected(string $filename): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        try {
            $source = UploadedFile::fake()->image('source.png');
            $file = new UploadedFile($source->getRealPath(), $filename, null, null, true);
            app(MediaUploader::class)->upload($user, $file, 'Título', 'ALT');
            $this->fail('El nombre inseguro no debe aceptarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[TestWith([6001, 1])]
    #[TestWith([4000, 2001])]
    public function test_dimensions_and_total_pixels_are_limited(int $width, int $height): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        try {
            app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('large.png', $width, $height), 'Grande', 'ALT');
            $this->fail('No debe aceptar dimensiones excesivas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_empty_slug_falls_back_to_readable_name_and_long_names_are_bounded(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $uploader = app(MediaUploader::class);
        $first = $uploader->upload($user, UploadedFile::fake()->image('😀.png', 20, 10), 'Imagen', 'ALT')['asset']->getFirstMedia('images');
        $second = $uploader->upload($user, UploadedFile::fake()->image(str_repeat('a', 200).'.png', 30, 10), 'Larga', 'ALT')['asset']->getFirstMedia('images');
        $this->assertSame('imagen.png', $first->file_name);
        $this->assertSame(str_repeat('a', 160).'.png', $second->file_name);
    }

    public function test_batch_reports_partial_results_and_duplicates_without_removing_successful_uploads(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('shared.png');
        $results = app(MediaUploader::class)->uploadBatch($user, [
            ['image' => $file, 'name' => 'Original', 'alt_text' => 'ALT'],
            ['image' => UploadedFile::fake()->create('bad.php', 10), 'name' => 'Inválida', 'alt_text' => 'ALT'],
            ['image' => $file, 'name' => 'Duplicada', 'alt_text' => 'Otro ALT'],
        ]);
        $this->assertSame(['created', 'failed', 'existing'], array_column($results, 'status'));
        $this->assertArrayHasKey('image', $results[1]['errors']);
        $this->assertTrue($results[0]['asset']->is($results[2]['asset']));
        $this->assertDatabaseCount('media', 1);
        $this->assertCount(3, Storage::disk('public')->allFiles());
    }

    #[TestWith([11, 1])]
    #[TestWith([6, 5000])]
    public function test_batch_limits_are_checked_before_persistence(int $count, int $kilobytes): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $items = array_fill(0, $count, ['image' => UploadedFile::fake()->image('image.png')->size($kilobytes), 'name' => 'Nombre', 'alt_text' => 'ALT']);
        try {
            app(MediaUploader::class)->uploadBatch($user, $items);
            $this->fail('El lote excesivo no debe guardarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('images', $exception->errors());
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_rechecks_permissions_from_database(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        User::query()->whereKey($user->id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('image.png'), 'Nombre', 'ALT');
    }

    public function test_throttle_blocks_excessive_ingestion_without_persisting(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        config(['media-library.upload_limits.per_minute' => 1]);
        $file = UploadedFile::fake()->image('image.png');
        app(MediaUploader::class)->upload($user, $file, 'Nombre', 'ALT');
        try {
            app(MediaUploader::class)->upload($user, $file, 'Nombre', 'ALT');
            $this->fail('Debe limitar subidas excesivas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('media', 1);
    }

    public function test_conversion_failure_rolls_back_new_records_and_cleans_only_new_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $uploader = app(MediaUploader::class);
        $existing = $uploader->upload($user, UploadedFile::fake()->image('existing.png', 10, 10), 'Existente', 'ALT');
        $files = Storage::disk('public')->allFiles();
        $this->mock(FileManipulator::class)->shouldReceive('createDerivedFiles')->andThrow(new RuntimeException('Failure'));
        $file = UploadedFile::fake()->image('new.png', 20, 10);
        try {
            $uploader->upload($user, $file, 'Nueva', 'ALT');
            $this->fail('Una conversión fallida no debe dejar un activo nuevo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media', 1);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertModelExists($existing['asset']);
        $this->assertFileExists($file->getRealPath());
    }

    public function test_filament_creation_uses_uploader_and_duplicate_submission_returns_existing_record(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $file = UploadedFile::fake()->image('Equipo Ávila.png');
        foreach ([1, 2] as $attempt) {
            Livewire::test(CreateMediaAsset::class)->fillForm(['name' => 'Equipo', 'alt_text' => 'Personas', 'image' => $file])
                ->call('create')->assertHasNoFormErrors()->assertNotified();
        }
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media', 1);
        $this->assertSame('equipo-avila.png', MediaAsset::first()->getFirstMedia('images')->file_name);
    }

    public function test_filament_displays_conversion_failure_on_the_image_field_and_allows_retry(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $this->mock(FileManipulator::class)->shouldReceive('createDerivedFiles')->andThrow(new RuntimeException('Failure'));
        Livewire::test(CreateMediaAsset::class)
            ->fillForm(['name' => 'Equipo', 'alt_text' => 'Personas', 'image' => UploadedFile::fake()->image('equipo.png')])
            ->call('create')->assertHasFormErrors(['image'])->assertSet('isCreating', false);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_disk_write_failure_does_not_leave_records_or_files(): void
    {
        $disk = Storage::fake('public');
        $user = User::factory()->create();
        $filesystem = $this->mock(Factory::class)
            ->shouldReceive('disk')->with('public')->andReturn(
                \Mockery::mock($disk)->shouldReceive('put')->andReturn(false)->getMock()
            )->getMock();
        $this->app->instance(Filesystem::class, new Filesystem($filesystem));
        try {
            app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('equipo.png'), 'Equipo', 'Personas');
            $this->fail('La escritura fallida no debe guardarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_duplicate_with_missing_original_is_reported_without_replacing_the_asset(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('original.png');
        $uploader = app(MediaUploader::class);
        $asset = $uploader->upload($user, $file, 'Original', 'ALT')['asset'];
        Storage::disk('public')->delete($asset->getFirstMedia('images')->getPathRelativeToRoot());
        $files = Storage::disk('public')->allFiles();
        try {
            $uploader->upload($user, $file, 'Duplicado', 'ALT');
            $this->fail('El original ausente debe informarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('original no está disponible', $exception->errors()['image'][0]);
        }
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertSame('Original', $asset->fresh()->name);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_truncated_image_with_valid_header_is_rejected_before_persistence(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $source = UploadedFile::fake()->image('source.png', 40, 20);
        $file = UploadedFile::fake()->createWithContent('corrupt.png', substr(file_get_contents($source->getRealPath()), 0, 33));
        try {
            app(MediaUploader::class)->upload($user, $file, 'Corrupta', 'ALT');
            $this->fail('Una cabecera válida no demuestra que el archivo pueda decodificarse.');
        } catch (ValidationException $exception) {
            $this->assertSame('No se pudo decodificar la imagen.', $exception->errors()['image'][0]);
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
