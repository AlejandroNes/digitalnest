<?php

namespace App\Filament\Resources\Deals;

use App\DealType;
use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Support\ContentFields;
use App\Models\Deal;
use App\PublicationStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static ?string $modelLabel = 'oferta';

    protected static ?string $pluralModelLabel = 'Ofertas y cupones';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Ofertas';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Oferta')->schema([
                        Select::make('tool_id')->label('App')->relationship('tool', 'name')
                            ->searchable()->preload()->required(),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ContentFields::title('title', 'Título'),
                            ContentFields::slug(),
                        ]),
                        Textarea::make('description')->label('Descripción')->rows(3)->maxLength(2000),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('type')->label('Tipo')->options(DealType::class)->live()
                                ->default(DealType::Discount->value)->required()->rule(Rule::enum(DealType::class)),
                            TextInput::make('code')->label('Código de cupón')->maxLength(64)
                                ->required(fn (Get $get): bool => in_array($get('type'), [DealType::Coupon, DealType::Coupon->value], true)),
                        ]),
                        TextInput::make('url')->label('Enlace de la oferta')->url()->maxLength(2048)
                            ->rule('regex:/^https?:\/\//i')
                            ->helperText('Vacío: se usa el enlace de afiliado o la web de la app.'),
                    ]),
                    Section::make('Precio')->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            TextInput::make('original_price')->label('Precio original')->numeric()->minValue(0)->maxValue(99999999),
                            TextInput::make('deal_price')->label('Precio con oferta')->numeric()->minValue(0)->maxValue(99999999)
                                ->lte('original_price'),
                            TextInput::make('discount_percent')->label('Descuento %')->integer()->minValue(1)->maxValue(100)
                                ->helperText('Vacío: se calcula con los precios.'),
                            Select::make('currency')->label('Moneda')->options(['USD' => 'USD', 'EUR' => 'EUR'])
                                ->default('USD')->required()->in(['USD', 'EUR']),
                        ]),
                    ]),
                    Section::make('SEO')->collapsible()->collapsed()->schema([
                        TextInput::make('seo_title')->label('Título SEO')->maxLength(255),
                        Textarea::make('seo_description')->label('Descripción SEO')->maxLength(320),
                    ]),
                ]),
                Group::make()->columnSpan(['default' => 1, 'lg' => 1])->schema([
                    Section::make('Publicación')->schema([
                        Select::make('status')->label('Estado')->options(PublicationStatus::class)
                            ->default(PublicationStatus::Draft->value)->required()->rule(Rule::enum(PublicationStatus::class)),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')->helperText('Vacío: se publica al guardar.'),
                        DateTimePicker::make('starts_at')->label('Empieza'),
                        DateTimePicker::make('expires_at')->label('Caduca')->after('starts_at'),
                        DateTimePicker::make('verified_at')->label('Verificada el')->maxDate(now()->endOfDay()),
                        Toggle::make('is_featured')->label('Destacada')->default(false),
                        Toggle::make('is_exclusive')->label('Exclusiva')->default(false),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tool'))
            ->columns([
                TextColumn::make('title')->label('Oferta')->searchable()->sortable()->wrap()->weight('medium')
                    ->description(fn (Deal $record): ?string => $record->tool?->name),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('discount_percent')->label('Descuento')->suffix('%')->placeholder('—')->sortable(),
                TextColumn::make('code')->label('Código')->copyable()->placeholder('—')->fontFamily('mono'),
                TextColumn::make('expires_at')->label('Caduca')->dateTime('d/m/Y H:i')->sortable()->placeholder('Sin fecha')
                    ->color(fn (Deal $record): string => $record->isExpired() ? 'danger' : 'gray'),
                TextColumn::make('clicks')->label('Clics')->numeric()->sortable(),
                IconColumn::make('is_featured')->label('Destacada')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(DealType::class),
                SelectFilter::make('status')->label('Estado')->options(PublicationStatus::class),
                SelectFilter::make('tool')->label('App')->relationship('tool', 'name')->searchable()->preload(),
                Filter::make('active')->label('Activas')->query(fn (Builder $query): Builder => $query->active()),
                Filter::make('expiring')->label('Caducan en 7 días')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '>', now())->expiringWithin(7)),
                Filter::make('expired')->label('Caducadas')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<=', now())),
            ])
            ->recordActions([
                Action::make('verify')->label('Verificada hoy')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->authorize(fn (Deal $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(fn (Deal $record) => $record->update(['verified_at' => now()])),
                ReplicateAction::make()->label('Duplicar')
                    ->excludeAttributes(['clicks', 'published_at'])
                    ->mutateRecordDataUsing(fn (array $data): array => [
                        ...$data,
                        'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(5)),
                        'status' => PublicationStatus::Draft->value,
                        'is_featured' => false,
                    ])
                    ->successRedirectUrl(fn (Deal $replica): string => self::getUrl('edit', ['record' => $replica])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No hay ofertas')
            ->emptyStateDescription('Crea la primera oferta o cupón de una app.')
            ->emptyStateIcon(Heroicon::OutlinedTicket)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'edit' => EditDeal::route('/{record}/edit'),
        ];
    }
}
