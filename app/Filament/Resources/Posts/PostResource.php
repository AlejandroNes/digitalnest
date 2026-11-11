<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Support\BlogEditor;
use App\Filament\Support\ContentFields;
use App\Filament\Support\SeoFields;
use App\Models\Post;
use App\PostStatus;
use App\Services\ContentHtml;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $modelLabel = 'artículo';

    protected static ?string $pluralModelLabel = 'Artículos';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Blog y Noticias';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    public static function getNavigationBadge(): ?string
    {
        if (! Gate::allows('viewAny', Post::class)) {
            return null;
        }

        $count = Post::query()->where('status', PostStatus::Draft)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Artículos en borrador';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Contenido principal')
                            ->schema([
                                BlogEditor::title('title', 'Título'),
                                ContentFields::slug()->helperText('Cambiar la URL de un artículo ya público crea una redirección 301 desde la URL anterior. El título no cambia una URL personalizada.'),
                                Textarea::make('excerpt')
                                    ->label('Extracto')
                                    ->maxLength(2000)
                                    ->rows(3)
                                    ->helperText('Resumen breve visible en listados y tarjetas.'),
                                ContentFields::richText('content', 'Contenido')->rules([
                                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                        if (! is_string($value) && ! is_array($value) && $value !== null) {
                                            $fail('El contenido del artículo no es válido.');

                                            return;
                                        }
                                        $text = html_entity_decode(strip_tags(ContentHtml::sanitize(RichContentRenderer::make($value)->toHtml())), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        if (preg_replace('/[\\s\\p{Z}\\p{Cf}]+/u', '', $text) === '') {
                                            $fail('Escribe contenido visible para el artículo.');
                                        }
                                    },
                                ]),
                            ]),
                        SeoFields::section(),
                    ]),
                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Publicación')
                            ->schema([
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(PostStatus::class)
                                    ->default('draft')
                                    ->required()
                                    ->rule(Rule::enum(PostStatus::class))
                                    ->live(),
                                DateTimePicker::make('published_at')
                                    ->label('Fecha de publicación')
                                    ->seconds(false)
                                    ->timezone('UTC')
                                    ->required(fn (Get $get): bool => in_array($get('status'), [PostStatus::Published, 'published'], true))
                                    ->beforeOrEqual('now')
                                    ->helperText('Fecha en UTC. Los borradores no aparecen en consultas públicas.'),
                            ]),
                        Section::make('Clasificación e Imagen')
                            ->schema([
                                Select::make('categories')
                                    ->label('Categorías')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->rules(['array'])
                                    ->nestedRecursiveRules(['integer', 'exists:post_categories,id']),
                                ContentFields::image()->imageContext('blog'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('title')->wrap()->label('Título')->searchable()->sortable(), TextColumn::make('status')->wrap()->label('Estado')->badge(), TextColumn::make('published_at')->wrap()->label('Publicación')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('status')->label('Estado')->options(PostStatus::class)])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
