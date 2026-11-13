<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\LegalPage;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use XMLWriter;

class Sitemap
{
    public const MAX_URLS = 10000;

    private const TTL = 600;

    /** @return array{prefix: string, pages: int, count: int, generated_at: string} */
    public function manifest(): array
    {
        $key = $this->manifestKey();
        $future = Post::query()->where('status', 'published')->where('published_at', '>', now())->min('published_at');
        $ttl = $future ? max(1, min(self::TTL, (int) now()->diffInSeconds(Carbon::parse($future)))) : self::TTL;

        return Cache::memo()->remember($key, $ttl, fn (): array => $this->generate());
    }

    public function xml(?int $page = null): string
    {
        if (! SiteIndexing::allows()) {
            abort_if($page !== null, 404);
            $xml = $this->document('urlset');
            $xml->endElement();
            $xml->endDocument();

            return $xml->outputMemory();
        }
        $manifest = $this->manifest();
        if ($page !== null) {
            abort_unless($manifest['pages'] > 1 && $page >= 1 && $page <= $manifest['pages'], 404);
        } elseif ($manifest['pages'] > 1) {
            $xml = $this->document('sitemapindex');
            for ($number = 1; $number <= $manifest['pages']; $number++) {
                $xml->startElement('sitemap');
                $xml->writeElement('loc', InternalUrl::absolute(route('sitemap.part', ['page' => $number], false)));
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endDocument();

            return $xml->outputMemory();
        }
        $xml = Cache::memo()->get($manifest['prefix'].($page ?? 1));
        if (! is_string($xml)) {
            Cache::memo()->forget($this->manifestKey());
            $manifest = $this->manifest();
            $xml = Cache::memo()->get($manifest['prefix'].($page ?? 1));
        }
        abort_unless(is_string($xml), 503);

        return $xml;
    }

    private function manifestKey(): string
    {
        return 'corporate.sitemap.manifest.'.hash('sha256', config('app.url').'|'.Cache::memo()->get(ApplicationCache::SITEMAP_REVISION, 'initial').'|'.$this->maxUrls());
    }

    /** @return array{prefix: string, pages: int, count: int, generated_at: string} */
    private function generate(): array
    {
        $prefix = 'corporate.sitemap.part.'.Str::uuid().'.';
        $number = 1;
        $count = 0;
        $inPage = 0;
        $xml = $this->document('urlset');
        foreach ($this->entries() as [$path, $modified]) {
            if ($inPage === $this->maxUrls()) {
                $this->storePage($xml, $prefix.$number++);
                $xml = $this->document('urlset');
                $inPage = 0;
            }
            $xml->startElement('url');
            $xml->writeElement('loc', InternalUrl::absolute($path));
            if ($modified) {
                $xml->writeElement('lastmod', $modified);
            }
            $xml->endElement();
            $inPage++;
            $count++;
        }
        $this->storePage($xml, $prefix.$number);

        return ['prefix' => $prefix, 'pages' => $number, 'count' => $count, 'generated_at' => now()->toIso8601String()];
    }

    private function storePage(XMLWriter $xml, string $key): void
    {
        $xml->endElement();
        $xml->endDocument();
        Cache::memo()->put($key, $xml->outputMemory(), self::TTL + 60);
    }

    private function document(string $root): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xml;
    }

    private function maxUrls(): int
    {
        return max(1, min(self::MAX_URLS, (int) config('seo.sitemap_max_urls', self::MAX_URLS)));
    }

    /** @return Generator<int, array{string, ?string}> */
    private function entries(): Generator
    {
        yield ['/', null];
        if (Route::has('contact.create')) {
            yield [route('contact.create', absolute: false), null];
        }
        if (Route::has('projects.index') && config('features.corporate_modules_enabled')) {
            $modified = Project::query()->published()->max('updated_at');
            yield [route('projects.index', absolute: false), $modified ? Carbon::parse($modified)->toAtomString() : null];
        }
        if (Route::has('posts.index') && ($postsModified = Post::query()->published()->max('updated_at'))) {
            yield [route('posts.index', absolute: false), Carbon::parse($postsModified)->toAtomString()];
            foreach (PostCategory::query()->whereHas('posts', fn (Builder $query): Builder => $query->published())->orderBy('id')->select(['id', 'slug', 'updated_at'])->cursor() as $postCategory) {
                yield [route('posts.category', ['slug' => $postCategory->slug], false), $postCategory->updated_at?->toAtomString()];
            }
        }
        foreach (Post::query()->published()->where('seo_noindex', false)->orderBy('id')->select(['slug', 'seo_canonical', 'updated_at'])->lazy(1000) as $post) {
            $path = route('posts.show', ['slug' => $post->slug], false);
            if (InternalUrl::path($path) && (! $post->seo_canonical || $post->seo_canonical === $path)) {
                yield [$path, $post->updated_at?->toAtomString()];
            }
        }
        foreach (config('features.corporate_modules_enabled') ? Service::query()->active()->where('seo_noindex', false)->orderBy('id')->select(['slug', 'seo_canonical', 'updated_at'])->lazy(1000) : [] as $service) {
            $path = route('services.show', ['slug' => $service->slug], false);
            if (InternalUrl::path($path) && (! $service->seo_canonical || $service->seo_canonical === $path)) {
                yield [$path, $service->updated_at?->toAtomString()];
            }
        }
        yield from $this->marketplaceEntries();
        foreach (LegalPage::query()->published()->select(['type', 'content_updated_at'])->cursor() as $page) {
            yield [route('legal.show', ['type' => $page->type->value], false), $page->content_updated_at?->toAtomString()];
        }
        foreach (['pages.about', 'pages.method', 'pages.affiliates'] as $fixedPage) {
            if (Route::has($fixedPage)) {
                yield [route($fixedPage, absolute: false), null];
            }
        }
    }

    /**
     * Deals app sections: explore, deals feed, rankings, sectors, app profiles, active deals and rankings.
     *
     * @return Generator<int, array{string, ?string}>
     */
    private function marketplaceEntries(): Generator
    {
        if (! Route::has('deals.index')) {
            return;
        }
        $latestDeal = Deal::query()->active()->max('updated_at');
        yield [route('explore', absolute: false), null];
        yield [route('deals.index', absolute: false), $latestDeal ? Carbon::parse($latestDeal)->toAtomString() : null];
        yield [route('rankings.index', absolute: false), null];

        foreach (ToolCategory::query()->active()->where('seo_noindex', false)->orderBy('id')->select(['slug', 'updated_at'])->cursor() as $sector) {
            yield [route('sectors.show', ['slug' => $sector->slug], false), $sector->updated_at?->toAtomString()];
        }
        foreach (Tool::query()->published()->where('seo_noindex', false)->orderBy('id')->select(['id', 'slug', 'updated_at'])->lazy(1000) as $tool) {
            yield [route('apps.show', ['slug' => $tool->slug], false), $tool->updated_at?->toAtomString()];
        }
        foreach (Deal::query()->active()->orderBy('id')->select(['id', 'tool_id', 'slug', 'updated_at'])->lazy(1000) as $deal) {
            yield [route('deals.show', ['slug' => $deal->slug], false), $deal->updated_at?->toAtomString()];
        }
        foreach (Ranking::query()->published()->where('seo_noindex', false)->orderBy('id')->select(['slug', 'updated_at'])->cursor() as $ranking) {
            yield [route('rankings.show', ['slug' => $ranking->slug], false), $ranking->updated_at?->toAtomString()];
        }
    }
}
