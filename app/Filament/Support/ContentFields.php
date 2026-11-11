<?php

namespace App\Filament\Support;

use App\Filament\Forms\Components\MediaPicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class ContentFields
{
    public static function title(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->required()->maxLength(255)
            ->live(onBlur: true)->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                if ($operation === 'create') {
                    $set('slug', Str::slug($state ?? ''));
                }
            });
    }

    public static function slug(): TextInput
    {
        return TextInput::make('slug')->label('Slug')->required()->maxLength(255)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true);
    }

    public static function richText(string $name, string $label): RichEditor
    {
        return RichEditor::make($name)->label($label)->required()->maxLength(50000)
            ->fileAttachments(false)->toolbarButtons(['bold', 'italic', 'underline', 'strike', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link', 'undo', 'redo'])
            ->columnSpanFull();
    }

    public static function image(string $name = 'image_id', string $relationship = 'image'): MediaPicker
    {
        return MediaPicker::make($name)->label('Imagen de la biblioteca')
            ->helperText('Elige una imagen existente o sube una nueva sin salir del formulario.');
    }

    public static function order(): TextInput
    {
        return TextInput::make('sort_order')->label('Orden')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required();
    }

    public static function active(): Toggle
    {
        return Toggle::make('is_active')->label('Activo')->default(true)->required();
    }
}
