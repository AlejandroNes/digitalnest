<?php

namespace App\Rules;

use App\Models\MediaAsset;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaSelection implements ValidationRule
{
    public function __construct(public string $context = 'content') {}

    public static function maxKilobytes(string $context): int
    {
        return match ($context) {
            'logo', 'assistant_avatar' => 2048, 'favicon' => 256, default => (int) config('media-library.upload_limits.file_kb')
        };
    }

    /** @return list<string> */
    public static function mimeTypes(string $context): array
    {
        return $context === 'favicon' ? ['image/png'] : ['image/jpeg', 'image/png', 'image/webp'];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $media = (is_int($value) || (is_string($value) && ctype_digit($value)))
            ? Media::query()->with('model')->find($value) : null;
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        if (! $media || $media->collection_name !== 'images' || ! $media->model instanceof MediaAsset
            || ! $user || ! Gate::forUser($user)->allows('view', $media->model)
            || ! in_array($media->mime_type, self::mimeTypes($this->context), true)) {
            $fail('Selecciona una imagen válida y autorizada de la biblioteca.');

            return;
        }
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        if (! $disk->exists($path) || ! in_array($disk->mimeType($path), self::mimeTypes($this->context), true) || $disk->size($path) > self::maxKilobytes($this->context) * 1024) {
            $fail('El original no está disponible o no cumple el formato y tamaño permitidos para este campo.');
        }
    }
}
