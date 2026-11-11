<?php

namespace App\Filament\Support;

use App\Services\InternalUrl;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class SeoFields
{
    public static function section(): Section
    {
        return Section::make('SEO')->collapsible()->schema([
            TextInput::make('seo_title')->label('Título SEO')->maxLength(255),
            Textarea::make('seo_description')->label('Descripción SEO')->maxLength(320),
            ContentFields::image('seo_image_id', 'seoImage')->label('Imagen Open Graph')->imageContext('seo'),
            TextInput::make('seo_canonical')->label('Canonical')->maxLength(255)
                ->helperText('Opcional: ruta pública interna sin parámetros. Por defecto se utiliza la URL de este contenido.')
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! InternalUrl::path($value) || InternalUrl::protectedPath($value)) {
                        $fail('Usa una ruta pública interna, sin dominio ni parámetros.');
                    }
                }]),
            Toggle::make('seo_noindex')->label('Excluir de indexación')->default(false)->required(),
        ])->columns(['default' => 1, 'md' => 2])->columnSpanFull();
    }
}
