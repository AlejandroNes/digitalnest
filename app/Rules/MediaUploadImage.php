<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

class MediaUploadImage implements ValidationRule
{
    public static function rules(): array
    {
        return ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
            'max:'.config('media-library.upload_limits.file_kb'), new self];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Selecciona una imagen válida.');

            return;
        }
        $name = $value->getClientOriginalName();
        $segments = array_slice(explode('.', strtolower($name)), 1);
        if (preg_match('/[\/\\\\]|\p{C}|\.\./u', $name) !== 0
            || array_intersect($segments, FileAdder::$defaultDisallowedExtensions)) {
            $fail('El nombre del archivo contiene una ruta o una extensión no permitida.');

            return;
        }
        $size = @getimagesize($value->getRealPath());
        $extensions = match ($size['mime'] ?? null) {
            'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], default => [],
        };
        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions, true)) {
            $fail('El formato real de la imagen no coincide con su extensión.');

            return;
        }
        if ($size[0] > config('media-library.upload_limits.dimension')
            || $size[1] > config('media-library.upload_limits.dimension')
            || config('media-library.upload_limits.pixels') < $size[0] * $size[1]) {
            $fail('La imagen supera 6000 píxeles por lado o 8 millones de píxeles totales.');
        }
    }
}
