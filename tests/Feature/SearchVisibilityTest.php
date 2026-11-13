<?php

use App\Models\GeneralSetting;
use App\Models\LegalPage;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Services\Seo;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    DB::select('select 1');
    app()->instance('env', 'production');
    config(['app.url' => 'https://corporate.example.com']);
});

it('renders global defaults safely with canonical free of query parameters', function (): void {
    SearchSetting::factory()->create(['default_title' => '\"><script>alert(1)</script>', 'default_description' => 'Descripción & empresa', 'indexable' => true, 'verification_token' => 'Safe_token-123', 'verification_enabled' => true]);
    $this->get('/?utm_source=test')->assertOk()
        ->assertSee('<link rel="canonical" href="https://corporate.example.com/">', false)
        ->assertSee('content="index, follow"', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('name="google-site-verification" content="Safe_token-123"', false)
        ->assertSee('Descripción &amp; empresa', false);
});

it('uses company defaults and disables indexing without explicit configuration', function (): void {
    GeneralSetting::factory()->create(['id' => 1, 'company_name' => 'Empresa ejemplo', 'description' => 'Descripción base']);
    $this->get('/')->assertOk()->assertSee('<title>Empresa ejemplo</title>', false)
        ->assertSee('content="Descripción base"', false)->assertSee('content="noindex, nofollow"', false);
    $this->assertDatabaseCount('search_settings', 0);
});

it('renders individual metadata and fallback metadata for posts and services', function (): void {
    SearchSetting::factory()->create(['indexable' => true]);
    $post = Post::factory()->published()->create(['seo_title' => 'Título SEO', 'seo_description' => 'Descripción SEO', 'seo_canonical' => '/blog/principal']);
    $service = Service::factory()->create(['name' => 'Servicio propio', 'summary' => 'Resumen propio', 'seo_noindex' => true]);
    $this->get('/blog/'.$post->slug.'?tracking=1')->assertOk()->assertSee('<title>Título SEO</title>', false)
        ->assertSee('content="Descripción SEO"', false)->assertSee('href="https://corporate.example.com/blog/principal"', false)
        ->assertSee('property="og:type" content="article"', false)->assertSee('name="twitter:title"', false);
    $this->get('/servicios/'.$service->slug)->assertOk()->assertSee('<title>Servicio propio</title>', false)
        ->assertSee('content="Resumen propio"', false)->assertSee('content="noindex, nofollow"', false);
    $post->update(['seo_title' => null, 'seo_description' => null, 'seo_canonical' => null]);
    $this->get('/blog/'.$post->slug)->assertOk()->assertSee('<title>'.$post->title.'</title>', false)->assertSee($post->excerpt);
});

it('never serves drafts future posts inactive services or unpublished legal pages', function (): void {
    $draft = Post::factory()->create();
    $future = Post::factory()->published()->create(['published_at' => now()->addDay()]);
    $service = Service::factory()->inactive()->create();
    LegalPage::factory()->create(['type' => 'privacy']);
    foreach (['/blog/'.$draft->slug, '/blog/'.$future->slug, '/servicios/'.$service->slug, '/legal/privacy', '/legal/arbitrary'] as $url) {
        $this->get($url)->assertNotFound();
    }
});

it('generates a fresh valid sitemap with only canonical published indexable content', function (): void {
    SearchSetting::factory()->create(['indexable' => true]);
    $post = Post::factory()->published()->create(['slug' => 'visible']);
    Post::factory()->create(['slug' => 'draft']);
    Post::factory()->published()->create(['slug' => 'future', 'published_at' => now()->addDay()]);
    Post::factory()->published()->create(['slug' => 'hidden', 'seo_noindex' => true]);
    Post::factory()->published()->create(['slug' => 'duplicate', 'seo_canonical' => '/blog/visible']);
    Service::factory()->create(['slug' => 'active']);
    Service::factory()->inactive()->create(['slug' => 'inactive']);
    Service::factory()->create(['slug' => 'private', 'seo_noindex' => true]);
    LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    LegalPage::factory()->create(['type' => 'terms']);
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertHeader('Cache-Control', 'no-store, private');
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    $urls = array_map(fn ($entry): string => (string) $entry->loc, iterator_to_array($xml->url, false));
    expect($urls)->toHaveCount(13)->toContain('https://corporate.example.com/explorar', 'https://corporate.example.com/ofertas', 'https://corporate.example.com/top', 'https://corporate.example.com/como-probamos', 'https://corporate.example.com/', 'https://corporate.example.com/contacto', 'https://corporate.example.com/proyectos', 'https://corporate.example.com/blog', 'https://corporate.example.com/blog/visible', 'https://corporate.example.com/servicios/active', 'https://corporate.example.com/legal/privacy');
    $post->update(['status' => 'draft']);
    $this->get('/sitemap.xml')->assertDontSee('/blog/visible', false)->assertDontSee('/admin', false);
});

it('disables sitemap and crawling when indexing is off or site is in maintenance', function (): void {
    $settings = SearchSetting::factory()->create();
    Post::factory()->published()->create();
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:', false);
    expect(simplexml_load_string($this->get('/sitemap.xml')->getContent())->url)->toHaveCount(0);
    $settings->update(['indexable' => true]);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin', false)->assertSee('Sitemap: https://corporate.example.com/sitemap.xml', false);
    GeneralSetting::factory()->create(['id' => 1, 'maintenance_enabled' => true]);
    Cache::forget('general_settings');
    $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    expect(simplexml_load_string($this->get('/sitemap.xml')->getContent())->url)->toHaveCount(0);
});

it('uses SEO images before content images before global images and protects their files', function (): void {
    Storage::fake('public');
    $global = MediaAsset::factory()->create();
    $globalMedia = $global->addMedia(UploadedFile::fake()->image('global.png'))->toMediaCollection('images');
    SearchSetting::factory()->create(['og_image_id' => $globalMedia->id]);
    $asset = MediaAsset::factory()->create();
    $media = $asset->addMedia(UploadedFile::fake()->image('individual.png'))->toMediaCollection('images');
    $post = Post::factory()->published()->create(['seo_image_id' => $media->id]);
    $this->get('/blog/'.$post->slug)->assertSee(MediaAsset::mediaUrl($media), false)->assertSee('summary_large_image', false);
    expect(fn () => $asset->delete())->toThrow(ValidationException::class);
    expect(fn () => $global->delete())->toThrow(ValidationException::class);
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    $post->update(['seo_image_id' => null, 'image_id' => $media->id]);
    $this->get('/blog/'.$post->slug)->assertSee(MediaAsset::mediaUrl($media), false);
    $post->update(['image_id' => null]);
    $this->get('/blog/'.$post->slug)->assertSee(MediaAsset::mediaUrl($globalMedia), false);
});

it('ignores malformed persisted Google tokens and sanitizes public rich content', function (): void {
    SearchSetting::factory()->create(['verification_token' => '\"><script>alert(1)</script>']);
    $post = Post::factory()->published()->create(['content' => '<p>Texto</p><script>alert(1)</script><img src=x onerror=alert(1)>']);
    $this->get('/blog/'.$post->slug)->assertOk()->assertSee('<p>Texto</p>', false)
        ->assertDontSee('google-site-verification', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('onerror=', false);
});

it('marks nonpublic model previews noindex and ignores reserved canonical overrides', function (): void {
    SearchSetting::factory()->create(['indexable' => true]);
    $request = Request::create('https://poisoned.test/blog/preview?tracking=1');
    $draft = Post::factory()->create(['seo_canonical' => '/admin']);
    $inactive = Service::factory()->inactive()->create();
    $metadata = app(Seo::class)->metadata($request, $draft);
    expect($metadata['robots'])->toBe('noindex, nofollow');
    expect($metadata['canonical'])->toBe('https://corporate.example.com/blog/preview');
    expect(app(Seo::class)->metadata($request, $inactive)['robots'])->toBe('noindex, nofollow');
});

it('uses current service content for SEO even when legacy overrides exist', function (): void {
    Storage::fake('public');
    SearchSetting::factory()->create(['indexable' => true]);
    $asset = MediaAsset::factory()->create();
    $image = $asset->addMedia(UploadedFile::fake()->image('service.png'))->toMediaCollection('images');
    $oldAsset = MediaAsset::factory()->create();
    $oldImage = $oldAsset->addMedia(UploadedFile::fake()->image('old-seo.png'))->toMediaCollection('images');
    $service = Service::factory()->create([
        'name' => 'Servicio actual', 'summary' => 'Resumen actual & claro', 'image_id' => $image->id,
        'seo_title' => 'SEO anterior', 'seo_description' => 'Descripción SEO anterior', 'seo_image_id' => $oldImage->id,
    ]);
    $this->get('/servicios/'.$service->slug)->assertOk()
        ->assertSee('<title>Servicio actual</title>', false)
        ->assertSee('name="description" content="Resumen actual &amp; claro"', false)
        ->assertSee('property="og:title" content="Servicio actual"', false)
        ->assertSee('property="og:image" content="https://corporate.example.com'.MediaAsset::mediaUrl($image).'"', false)
        ->assertSee('name="twitter:image" content="https://corporate.example.com'.MediaAsset::mediaUrl($image).'"', false)
        ->assertDontSee('SEO anterior')->assertDontSee(MediaAsset::mediaUrl($oldImage), false);
    $service->update(['name' => 'Nuevo nombre', 'summary' => 'Nuevo resumen']);
    $this->get('/servicios/'.$service->slug)->assertOk()->assertSee('<title>Nuevo nombre</title>', false)
        ->assertSee('name="description" content="Nuevo resumen"', false);
});

it('uses global fallback for service images and empty summaries while ignoring legacy overrides', function (): void {
    Storage::fake('public');
    $asset = MediaAsset::factory()->create();
    $image = $asset->addMedia(UploadedFile::fake()->image('global-service.png'))->toMediaCollection('images');
    SearchSetting::factory()->create(['default_description' => 'Descripción global', 'og_image_id' => $image->id]);
    $service = Service::factory()->create(['summary' => null, 'seo_description' => 'Descripción antigua']);
    $this->get('/servicios/'.$service->slug)->assertOk()
        ->assertSee('name="description" content="Descripción global"', false)
        ->assertSee('property="og:image" content="https://corporate.example.com'.MediaAsset::mediaUrl($image).'"', false);
});
