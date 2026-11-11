<?php

use App\Models\MediaAsset;
use App\Models\Post;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('renders actual responsive variants dimensions and lazy loading without regenerating files', function (): void {
    Storage::fake('public');
    $asset = MediaAsset::factory()->create();
    $media = $asset->addMedia(UploadedFile::fake()->image('responsive.png', 2000, 1000))->toMediaCollection('images');
    $original = Storage::disk('public')->get($media->getPathRelativeToRoot());
    $files = Storage::disk('public')->allFiles();
    $html = Blade::render('<x-content-image :media="$media" alt="&lt;script&gt;" />', ['media' => $media]);
    expect($html)->toContain('loading="lazy"', 'width="1600"', 'height="800"', 'srcset=', '320w', '1600w', '2000w')->not->toContain('<script>');
    expect(Storage::disk('public')->get($media->getPathRelativeToRoot()))->toBe($original);
    expect(Storage::disk('public')->allFiles())->toBe($files);
    $post = Post::factory()->published()->create(['image_id' => $media->id]);
    $this->get('/blog/'.$post->slug)->assertOk()->assertSee('loading="eager"', false)->assertSee('fetchpriority="high"', false);
});

it('omits missing derivatives and preserves referenced originals', function (): void {
    Storage::fake('public');
    $asset = MediaAsset::factory()->create();
    $media = $asset->addMedia(UploadedFile::fake()->image('original.png', 800, 400))->toMediaCollection('images');
    $post = Post::factory()->create(['seo_image_id' => $media->id]);
    expect(fn () => $asset->delete())->toThrow(ValidationException::class);
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->delete([$media->getPathRelativeToRoot('optimized'), $media->getPathRelativeToRoot('thumb')]);
    $html = Blade::render('<x-content-image :media="$media" />', ['media' => $media]);
    expect($html)->toContain('width="800"', 'height="400"', $media->getUrl())->not->toContain('srcset=');
    Storage::disk('public')->delete($media->getPathRelativeToRoot());
    expect(trim(Blade::render('<x-content-image :media="$media" />', ['media' => $media])))->toBe('');
});
