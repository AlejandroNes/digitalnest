<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrichMediaMetadataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_diagnosis_does_not_modify_records_or_files(): void
    {
        Storage::fake('public');
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('legacy.png', 40, 20))->toMediaCollection('images');
        $attributes = $media->fresh()->getAttributes();
        $files = Storage::disk('public')->allFiles();
        $this->artisan('app:enrich-media-metadata')->expectsOutput('Diagnóstico sin escritura.')->assertSuccessful();
        $this->assertNull($asset->fresh()->content_hash);
        $this->assertSame($attributes, $media->fresh()->getAttributes());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_write_enriches_without_inventing_original_names_or_moving_files_and_is_idempotent(): void
    {
        Storage::fake('public');
        $asset = MediaAsset::factory()->create();
        $media = $asset->addMedia(UploadedFile::fake()->image('legacy.png', 40, 20))->toMediaCollection('images');
        $fileName = $media->file_name;
        $original = Storage::disk('public')->get($media->getPathRelativeToRoot());
        $files = Storage::disk('public')->allFiles();
        $this->artisan('app:enrich-media-metadata', ['--write' => true, '--chunk' => 1])->assertSuccessful();
        $this->assertSame(hash('sha256', $original), $asset->fresh()->content_hash);
        $this->assertNull($media->fresh()->getCustomProperty('original_filename'));
        $this->assertSame(['width' => 40, 'height' => 20, 'mime' => 'image/png'], $media->fresh()->getCustomProperty('dimensions.original'));
        $attributes = $media->fresh()->getAttributes();
        $this->artisan('app:enrich-media-metadata', ['--write' => true])->assertSuccessful();
        $this->assertSame($attributes, $media->fresh()->getAttributes());
        $this->assertSame($fileName, $media->fresh()->file_name);
        $this->assertSame($original, Storage::disk('public')->get($media->getPathRelativeToRoot()));
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_historical_duplicates_and_missing_files_are_preserved_and_reported(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('same.png');
        $first = MediaAsset::factory()->create();
        $first->addMedia($file)->preservingOriginal()->toMediaCollection('images');
        $second = MediaAsset::factory()->create();
        $second->addMedia($file)->preservingOriginal()->toMediaCollection('images');
        $missing = MediaAsset::factory()->create();
        $files = Storage::disk('public')->allFiles();
        $this->artisan('app:enrich-media-metadata', ['--write' => true])
            ->expectsOutput("Medio {$second->id}: contenido duplicado del medio {$first->id}; se conservan ambos.")
            ->expectsOutput("Medio {$missing->id}: original ausente o disco no local; no se modifica.")
            ->assertSuccessful();
        $this->assertNotNull($first->fresh()->content_hash);
        $this->assertNull($second->fresh()->content_hash);
        $this->assertNull($missing->fresh()->content_hash);
        $this->assertDatabaseCount('media_assets', 3);
        $this->assertDatabaseCount('media', 2);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_invalid_chunk_is_rejected(): void
    {
        $this->artisan('app:enrich-media-metadata', ['--chunk' => 0])->assertFailed();
    }

    public function test_limit_and_cursor_only_enrich_the_requested_records(): void
    {
        Storage::fake('public');
        $assets = MediaAsset::factory()->count(3)->create();
        foreach ($assets as $asset) {
            $asset->addMedia(UploadedFile::fake()->image('legacy.png', $asset->id + 10, 20))->toMediaCollection('images');
        }
        $this->artisan('app:enrich-media-metadata', ['--write' => true, '--chunk' => 1, '--limit' => 1, '--after' => $assets[0]->id])->assertSuccessful();
        $this->assertNull($assets[0]->fresh()->content_hash);
        $this->assertNotNull($assets[1]->fresh()->content_hash);
        $this->assertNull($assets[2]->fresh()->content_hash);
    }
}
