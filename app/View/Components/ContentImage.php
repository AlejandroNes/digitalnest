<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ContentImage extends Component
{
    public ?string $src = null;

    public ?int $width = null;

    public ?int $height = null;

    public ?string $srcset = null;

    public function __construct(public ?Media $media = null, public string $alt = '', public bool $priority = false, public string $sizes = '100vw')
    {
        if (! $media) {
            return;
        }
        $variants = [];
        foreach (['optimized', 'thumb', ''] as $conversion) {
            if ($conversion !== '' && ! $media->hasGeneratedConversion($conversion)) {
                continue;
            }
            $diskName = $conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk);
            $disk = Storage::disk($diskName);
            $path = $media->getPathRelativeToRoot($conversion);
            if (! $disk->exists($path)) {
                continue;
            }
            $stored = $media->getCustomProperty('dimensions.'.($conversion ?: 'original'));
            $dimensions = is_array($stored) && is_int($stored['width'] ?? null) && is_int($stored['height'] ?? null) && $stored['width'] > 0 && $stored['height'] > 0
                ? [$stored['width'], $stored['height']]
                : (config("filesystems.disks.{$diskName}.driver") === 'local' ? @getimagesize($disk->path($path)) : false);
            $url = $media->getUrl($conversion);
            if ($this->src === null) {
                $this->src = $url;
                $this->width = $dimensions ? $dimensions[0] : null;
                $this->height = $dimensions ? $dimensions[1] : null;
            }
            if ($dimensions && $this->width && $this->height
                && abs($dimensions[0] / $dimensions[1] - $this->width / $this->height) < 0.01) {
                $variants[$dimensions[0]] ??= $url.' '.$dimensions[0].'w';
            }
        }
        ksort($variants);
        $this->srcset = count($variants) > 1 ? implode(', ', $variants) : null;
    }

    public function render(): View
    {
        return view('components.content-image');
    }
}
