<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\LegalPage;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Project;
use App\Models\SearchSetting;
use App\Models\Service;
use App\PostStatus;
use App\ProjectStatus;
use Illuminate\Http\Request;

class Seo
{
    /** @return array{title: string, description: string, canonical: string, robots: string, image: ?string, type: string, verification: ?string, image_alt: ?string, image_width: ?int, image_height: ?int, image_mime: ?string} */
    public function metadata(Request $request, Post|Service|LegalPage|Project|null $content = null): array
    {
        $settings = SearchSetting::current();
        $general = GeneralSetting::current(false);
        $canonicalPath = InternalUrl::path($content?->getAttribute('seo_canonical'));
        if (! $canonicalPath || InternalUrl::protectedPath($canonicalPath)) {
            $canonicalPath = '/'.ltrim($request->path(), '/');
        }
        $isPublic = match (true) {
            $content instanceof Post => $content->status === PostStatus::Published && $content->published_at && $content->published_at->lte(now()),
            $content instanceof Service => $content->is_active,
            $content instanceof LegalPage => $content->is_published,
            $content instanceof Project => $content->status === ProjectStatus::Published,
            default => true,
        };
        $imageMedia = $content instanceof Post || $content instanceof Project ? $content->seoImage : null;
        $image = MediaAsset::mediaUrl($imageMedia);
        if (! $image && ($content instanceof Post || $content instanceof Service || $content instanceof Project)) {
            $imageMedia = $content->image;
            $image = MediaAsset::mediaUrl($imageMedia);
        }
        if (! $image) {
            $imageMedia = $settings->ogImage;
            $image = MediaAsset::mediaUrl($imageMedia);
        }
        $conversion = $imageMedia && $image === $imageMedia->getUrl('optimized') ? 'optimized' : 'original';
        $dimensions = $imageMedia?->getCustomProperty('dimensions.'.$conversion);
        $imageAlt = $imageMedia?->model instanceof MediaAsset ? $imageMedia->model->alt_text : null;
        if ($image && ! preg_match('~^https?://~i', $image)) {
            $image = InternalUrl::absolute($image);
        }

        $title = $content instanceof Service
            ? $content->name
            : ($content?->getAttribute('seo_title') ?: ($content?->getAttribute('title') ?: $content?->getAttribute('name')));
        $description = $content instanceof Service
            ? $content->summary
            : ($content?->getAttribute('seo_description') ?: ($content?->getAttribute('short_description') ?: ($content?->getAttribute('excerpt') ?: $content?->getAttribute('summary'))));

        return [
            'title' => $title ?: ($settings->default_title ?: $general->company_name ?: config('app.name')),
            'description' => $description ?: ($settings->default_description ?: $general->description ?: ''),
            'canonical' => InternalUrl::absolute($canonicalPath),
            'robots' => SiteIndexing::allows($settings, $general) && $isPublic && ! $content?->getAttribute('seo_noindex') ? 'index, follow' : 'noindex, nofollow',
            'image' => $image,
            'image_alt' => $imageAlt,
            'image_width' => is_int($dimensions['width'] ?? null) && $dimensions['width'] > 0 ? $dimensions['width'] : null,
            'image_height' => is_int($dimensions['height'] ?? null) && $dimensions['height'] > 0 ? $dimensions['height'] : null,
            'image_mime' => $conversion === 'optimized' ? 'image/webp' : $imageMedia?->mime_type,
            'type' => $content instanceof Post ? 'article' : 'website',
            'verification' => $settings->validVerificationToken(),
        ];
    }
}
