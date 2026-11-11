<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['name', 'alt_text'])]
class MediaAsset extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media?->getCustomProperty('contact_assistant_avatar')) {
            $this->addMediaConversion('contact-avatar')->fit(Fit::Crop, 200, 200)
                ->format('webp')->quality(85)->nonQueued()->nonOptimized();
        }
        $this->addMediaConversion('optimized')->fit(Fit::Max, 1600, 1600)
            ->format('webp')->quality(80)->nonQueued()->nonOptimized();
        $this->addMediaConversion('thumb')->fit(Fit::Max, 320, 320)
            ->format('webp')->quality(80)->nonQueued()->nonOptimized();
    }

    public function isReferenced(): bool
    {
        return static::query()->whereKey($this->getKey())->whereUsed()->exists();
    }

    #[Scope]
    protected function withUsage(Builder $query): void
    {
        $query->addSelect('media_assets.*');
        foreach (static::referenceQueries() as $key => $reference) {
            $query->selectSub($reference->selectRaw('1')->limit(1), 'used_in_'.$key);
        }
    }

    #[Scope]
    protected function whereUsed(Builder $query, bool $used = true): void
    {
        $query->where(function (Builder $query) use ($used): void {
            foreach (static::referenceQueries() as $reference) {
                if ($used) {
                    $query->orWhereExists($reference->selectRaw('1')->toBase());
                } else {
                    $query->whereNotExists($reference->selectRaw('1')->toBase());
                }
            }
        });
    }

    /** @return array<string, Builder> */
    private static function referenceQueries(): array
    {
        $media = Media::query()->select('id')->whereColumn('model_id', 'media_assets.id')
            ->where('model_type', (new static)->getMorphClass());

        $references = [
            'posts' => Post::query()->where(fn (Builder $query): Builder => $query->whereIn('image_id', clone $media)->orWhereIn('seo_image_id', clone $media)),
            'services' => Service::query()->where(fn (Builder $query): Builder => $query->whereIn('image_id', clone $media)->orWhereIn('seo_image_id', clone $media)),
            'testimonials' => Testimonial::query()->whereIn('image_id', clone $media),
            'search' => SearchSetting::query()->whereIn('og_image_id', clone $media),
            'projects' => Project::query()->where(fn (Builder $query): Builder => $query->whereIn('image_id', clone $media)->orWhereIn('seo_image_id', clone $media)),
            'project_gallery' => ProjectImage::query()->whereIn('media_id', clone $media),
        ];
        if (ContactAssistantSetting::supportsConversation()) {
            $references['contact_assistant'] = ContactAssistantSetting::query()->whereIn('avatar_media_id', clone $media);
        }
        if (GeneralSetting::hasMediaReferences()) {
            $references['identity'] = GeneralSetting::query()->where(fn (Builder $query): Builder => $query
                ->whereIn('logo_media_id', clone $media)->orWhereIn('logo_dark_media_id', clone $media)->orWhereIn('favicon_media_id', clone $media));
        }

        return $references;
    }

    /** @return list<string> */
    public function usageLabels(): array
    {
        $labels = [
            'projects' => 'Proyectos y portafolio',
            'project_gallery' => 'Galerías de proyectos',
            'posts' => 'Artículos e imágenes SEO',
            'services' => 'Servicios e imágenes SEO',
            'testimonials' => 'Testimonios',
            'search' => 'Imagen social del sitio',
            'identity' => 'Logos y favicon del sitio',
            'contact_assistant' => 'Foto del asistente de contacto',
        ];

        return array_values(array_filter($labels, fn (string $key): bool => (bool) $this->getAttribute('used_in_'.$key), ARRAY_FILTER_USE_KEY));
    }

    public function delete(): ?bool
    {
        if ($this->isReferenced()) {
            throw ValidationException::withMessages(['image' => 'La imagen está en uso. Retira sus referencias antes de eliminarla.']);
        }

        return parent::delete();
    }

    public static function mediaUrl(?Media $media, string $conversion = 'optimized'): ?string
    {
        if (! $media) {
            return null;
        }
        if ($media->hasGeneratedConversion($conversion)
            && Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot($conversion))) {
            return $media->getUrl($conversion);
        }

        return Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()) ? $media->getUrl() : null;
    }

    public function imageUrl(): ?string
    {
        return self::mediaUrl($this->getFirstMedia('images'));
    }
}
