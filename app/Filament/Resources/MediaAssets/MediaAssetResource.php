<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\MediaLibraryTable;
use App\Models\MediaAsset;
use App\Rules\MediaUploadImage;
use App\Services\MediaImageGuidelines;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $modelLabel = 'medio';

    protected static ?string $pluralModelLabel = 'Biblioteca multimedia';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Sitio Corporativo';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('alt_text')->label('Texto alternativo (ALT)')->required()->maxLength(255),
            FileUpload::make('image')->label('Imagen')->storeFiles(false)
                ->placeholder('Arrastra una imagen o <span class="filepond--label-action">selecciona un archivo</span>')->columnSpanFull()
                ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->rules(MediaUploadImage::rules())
                ->maxSize(config('media-library.upload_limits.file_kb'))->preventFilePathTampering()->required()->visibleOn('create')
                ->helperText(MediaImageGuidelines::recommendation('content').' JPG, PNG o WebP, máximo '.(config('media-library.upload_limits.file_kb') / 1024).' MB, '.config('media-library.upload_limits.dimension').' píxeles por lado y '.(config('media-library.upload_limits.pixels') / 1000000).' millones de píxeles totales. Para cambiar el archivo, crea otro medio.'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }

    public static function table(Table $table): Table
    {
        return MediaLibraryTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'create' => CreateMediaAsset::route('/create'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
