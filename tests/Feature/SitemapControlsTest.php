<?php

namespace Tests\Feature;

use App\Models\GeneralSetting;
use App\Models\Post;
use App\Models\Project;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Services\Sitemap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SitemapControlsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://corporate.example.com']);
    }

    public function test_sitemap_can_be_disabled_independently_from_indexing_and_robots_follow_it(): void
    {
        $settings = SearchSetting::factory()->create(['indexable' => true, 'sitemap_enabled' => true]);
        app()->instance('env', 'production');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>https://corporate.example.com/proyectos</loc>', false);
        $this->get('/robots.txt')->assertSee('Sitemap: https://corporate.example.com/sitemap.xml', false);
        $settings->update(['sitemap_enabled' => false]);
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/sitemaps/1.xml')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertDontSee('Sitemap:')->assertDontSee("Disallow: /\n", false);
    }

    #[TestWith(['local', 'https://corporate.example.com'])]
    #[TestWith(['staging', 'https://corporate.example.com'])]
    #[TestWith(['production', 'http://localhost:8000'])]
    #[TestWith(['production', 'https://corporate.test'])]
    #[TestWith(['production', 'https://staging.example.com'])]
    #[TestWith(['production', 'http://192.168.1.2'])]
    public function test_non_public_sites_are_blocked_by_meta_headers_robots_and_empty_sitemaps(string $environment, string $url): void
    {
        SearchSetting::factory()->create(['indexable' => true, 'sitemap_enabled' => true]);
        app()->instance('env', $environment);
        config(['app.url' => $url]);
        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex, nofollow"', false);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(0, $xml->url);
        $this->assertStringNotContainsString($url, $response->getContent());
    }

    public function test_cached_sitemap_refreshes_after_publication_slug_deletion_and_indexing_changes(): void
    {
        SearchSetting::factory()->create(['indexable' => true]);
        app()->instance('env', 'production');
        $post = Post::factory()->create(['slug' => 'draft']);
        $this->get('/sitemap.xml')->assertDontSee('/blog/draft', false);
        $post->update(['status' => 'published', 'published_at' => now()]);
        $this->get('/sitemap.xml')->assertSee('/blog/draft', false);
        $post->update(['slug' => 'published']);
        $this->get('/sitemap.xml')->assertSee('/blog/published', false)->assertDontSee('/blog/draft', false);
        $post->update(['seo_noindex' => true]);
        $this->get('/sitemap.xml')->assertDontSee('/blog/published', false);
        $post->update(['seo_noindex' => false]);
        $this->get('/sitemap.xml')->assertSee('/blog/published', false);
        $post->delete();
        $this->get('/sitemap.xml')->assertDontSee('/blog/published', false);
    }

    public function test_future_publication_expires_the_sitemap_cache_when_the_post_becomes_public(): void
    {
        $this->travelTo(now()->startOfSecond());
        SearchSetting::factory()->create(['indexable' => true]);
        app()->instance('env', 'production');
        Post::factory()->published()->create(['slug' => 'scheduled', 'published_at' => now()->addMinute()]);
        $this->get('/sitemap.xml')->assertDontSee('/blog/scheduled', false);
        $this->travel(61)->seconds();
        $this->get('/sitemap.xml')->assertSee('/blog/scheduled', false);
    }

    public function test_large_sitemaps_split_into_an_index_and_valid_absolute_pages(): void
    {
        SearchSetting::factory()->create(['indexable' => true]);
        app()->instance('env', 'production');
        config(['seo.sitemap_max_urls' => 2]);
        Service::factory()->create(['slug' => 'consulting']);
        Project::factory()->published()->create(['slug' => 'portfolio-item']);
        $response = $this->get('/sitemap.xml')->assertOk();
        $index = simplexml_load_string($response->getContent());
        $this->assertNotFalse($index);
        $this->assertSame('sitemapindex', $index->getName());
        $this->assertCount(4, $index->sitemap);
        $this->assertSame('https://corporate.example.com/sitemaps/1.xml', (string) $index->sitemap[0]->loc);
        $urls = [];
        for ($page = 1; $page <= 4; $page++) {
            $xml = simplexml_load_string($this->get('/sitemaps/'.$page.'.xml')->assertOk()->getContent());
            $this->assertSame('urlset', $xml->getName());
            foreach ($xml->url as $entry) {
                $urls[] = (string) $entry->loc;
            }
        }
        $this->assertSame(['https://corporate.example.com/', 'https://corporate.example.com/contacto', 'https://corporate.example.com/proyectos', 'https://corporate.example.com/servicios/consulting', 'https://corporate.example.com/sobre-digitalnest', 'https://corporate.example.com/como-probamos', 'https://corporate.example.com/afiliados'], $urls);
        $this->get('/sitemaps/0.xml')->assertNotFound();
        $this->get('/sitemaps/5.xml')->assertNotFound();
        $this->get('/sitemaps/huge.xml')->assertNotFound();
    }

    public function test_missing_cached_part_recovers_and_relevant_real_dates_are_used(): void
    {
        SearchSetting::factory()->create(['indexable' => true]);
        app()->instance('env', 'production');
        $post = Post::factory()->published()->create(['slug' => 'dated']);
        $service = app(Sitemap::class);
        $manifest = $service->manifest();
        Cache::memo()->forget($manifest['prefix'].'1');
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertSame('', (string) $xml->url[0]->lastmod);
        $this->assertSame('/blog', parse_url((string) $xml->url[3]->loc, PHP_URL_PATH));
        $this->assertSame($post->updated_at->toAtomString(), (string) $xml->url[3]->lastmod);
        $this->assertSame($post->updated_at->toAtomString(), (string) $xml->url[4]->lastmod);
        $this->assertCount(8, $xml->url);
        GeneralSetting::factory()->create(['id' => 1, 'maintenance_enabled' => true]);
        $this->get('/sitemap.xml')->assertDontSee('/blog/dated', false);
    }
}
