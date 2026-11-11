<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $recordTitleAttribute = 'source';

    protected static ?string $modelLabel = 'redirección';

    protected static ?string $pluralModelLabel = 'Redirecciones';

    protected static string|\UnitEnum|null $navigationGroup = 'SEO y Analítica';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('source')->label('Origen')->required()->maxLength(255)->unique(ignoreRecord: true)
                ->helperText('Ruta interna anterior, por ejemplo /antiguo-articulo. No puede ser una ruta existente o reservada.'),
            TextInput::make('destination')->label('Destino')->required()->maxLength(255)->helperText('Ruta interna pública, por ejemplo /blog/nuevo-articulo. Sin dominio ni parámetros.'),
            Select::make('status_code')->label('Código')->options([301 => '301 — Permanente', 302 => '302 — Temporal'])->default(301)->required()->rule(Rule::in([301, 302])),
            Toggle::make('is_active')->label('Activa')->default(true)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('source')->wrap()->label('Origen')->searchable(),
            TextColumn::make('destination')->wrap()->label('Destino')->searchable(),
            TextColumn::make('status_code')->wrap()->label('Código'),
            IconColumn::make('is_active')->label('Activa')->boolean(),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
