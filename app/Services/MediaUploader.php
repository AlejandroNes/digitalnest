<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\MediaUploadImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class MediaUploader
{
    /** @return array{asset: MediaAsset, created: bool} */
    public function upload(User $user, UploadedFile $image, string $name, string $altText): array
    {
        $this->authorize($user);
        if (! Schema::hasColumn('media_assets', 'content_hash')) {
            throw ValidationException::withMessages(['image' => 'La actualización de la biblioteca requiere aplicar la migración de metadatos.']);
        }
        $this->limit($user);
        Validator::make(['image' => $image, 'name' => $name, 'alt_text' => $altText], [
            'image' => MediaUploadImage::rules(), 'name' => ['required', 'string', 'max:255'],
            'alt_text' => ['required', 'string', 'max:255'],
        ])->validate();
        $bytes = @file_get_contents($image->getRealPath());
        $decoded = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($decoded === false) {
            throw ValidationException::withMessages(['image' => 'No se pudo decodificar la imagen.']);
        }
        $hash = hash('sha256', $bytes);
        unset($decoded, $bytes);
        if ($existing = MediaAsset::query()->where('content_hash', $hash)->first()) {
            return $this->existing($user, $existing);
        }

        try {
            $asset = DB::transaction(function () use ($image, $name, $altText, $hash): MediaAsset {
                $asset = new MediaAsset(['name' => $name, 'alt_text' => $altText]);
                $asset->content_hash = $hash;
                $asset->save();
                try {
                    $mime = $image->getMimeType();
                    $extension = match ($mime) {
                        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                    };
                    $base = Str::limit(Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)), 160, '') ?: 'imagen';
                    $media = $asset->addMedia($image)->preservingOriginal()
                        ->usingFileName($base.'.'.$extension)
                        ->withCustomProperties(['original_filename' => $image->getClientOriginalName()])
                        ->toMediaCollection('images');
                    $dimensions = $this->dimensions($media);
                    if (count($dimensions) !== 3 || ! $media->hasGeneratedConversion('optimized') || ! $media->hasGeneratedConversion('thumb')) {
                        throw new \RuntimeException('No se generaron todas las variantes de la imagen.');
                    }
                    $media->setCustomProperty('dimensions', $dimensions);
                    $media->save();

                    return $asset;
                } catch (Throwable $exception) {
                    foreach ($asset->media()->get() as $media) {
                        try {
                            app(Filesystem::class)->removeAllFiles($media);
                        } catch (Throwable $cleanupException) {
                            Log::error('media.upload_cleanup_failed', ['media_id' => $media->id, 'exception' => $cleanupException::class]);
                        }
                    }
                    throw $exception;
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($existing = MediaAsset::query()->where('content_hash', $hash)->first()) {
                return $this->existing($user, $existing);
            }
            Log::error('media.upload_conflict_failed', ['exception' => $exception::class]);
            throw ValidationException::withMessages(['image' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
        } catch (Throwable $exception) {
            Log::error('media.upload_failed', ['exception' => $exception::class]);
            throw ValidationException::withMessages(['image' => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
        }

        return ['asset' => $asset, 'created' => true];
    }

    /**
     * @param  array<int, array{image: UploadedFile, name: string, alt_text: string}>  $items
     * @return array<int, array{status: string, asset: ?MediaAsset, errors: array}>
     */
    public function uploadBatch(User $user, array $items): array
    {
        $this->authorize($user);
        Validator::make(['images' => $items], [
            'images' => ['required', 'array', 'max:'.config('media-library.upload_limits.batch_files')],
            'images.*' => ['array'],
        ])->validate();
        $bytes = 0;
        foreach ($items as $item) {
            $bytes += ($item['image'] ?? null) instanceof UploadedFile ? $item['image']->getSize() : 0;
        }
        if ($bytes > config('media-library.upload_limits.batch_kb') * 1024) {
            throw ValidationException::withMessages(['images' => 'El lote supera 25 MB.']);
        }
        $results = [];
        foreach ($items as $item) {
            try {
                Validator::make($item, ['image' => ['required', 'file'], 'name' => ['required', 'string'], 'alt_text' => ['required', 'string']])->validate();
                $result = $this->upload($user, $item['image'], $item['name'], $item['alt_text']);
                $results[] = ['status' => $result['created'] ? 'created' : 'existing', 'asset' => $result['asset'], 'errors' => []];
            } catch (ValidationException $exception) {
                $results[] = ['status' => 'failed', 'asset' => null, 'errors' => $exception->errors()];
            }
        }

        return $results;
    }

    /** @return array<string, array{width: int, height: int, mime: string}> */
    public function dimensions(Media $media): array
    {
        $dimensions = [];
        foreach (['original' => '', 'optimized' => 'optimized', 'thumb' => 'thumb'] as $key => $conversion) {
            $disk = Storage::disk($conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk));
            if (config('filesystems.disks.'.($conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk)).'.driver') !== 'local') {
                continue;
            }
            $path = $media->getPathRelativeToRoot($conversion);
            if ($disk->exists($path) && ($size = @getimagesize($disk->path($path)))) {
                $dimensions[$key] = ['width' => $size[0], 'height' => $size[1], 'mime' => $size['mime']];
            }
        }

        return $dimensions;
    }

    private function authorize(User $user): void
    {
        $current = User::query()->find($user->getKey());
        abort_unless($current, 403);
        Gate::forUser($current)->authorize('create', MediaAsset::class);
    }

    private function limit(User $user): void
    {
        $key = 'media-upload:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, config('media-library.upload_limits.per_minute'))) {
            throw ValidationException::withMessages(['image' => 'Espera un minuto antes de subir más imágenes.']);
        }
        RateLimiter::hit($key, 60);
    }

    /** @return array{asset: MediaAsset, created: bool} */
    private function existing(User $user, MediaAsset $asset): array
    {
        $current = $user->fresh();
        abort_unless($current, 403);
        Gate::forUser($current)->authorize('view', $asset);
        $media = $asset->getFirstMedia('images');
        if (! $media || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            throw ValidationException::withMessages(['image' => 'Esta imagen ya está registrada, pero su original no está disponible. Revisa la biblioteca.']);
        }

        return ['asset' => $asset, 'created' => false];
    }
}
