<?php

namespace App\Filament\Support;

use App\Models\MediaAsset;
use App\Permission;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryTable
{
    public static function configure(Table $table, ?Closure $isSelected = null, ?Closure $selectionIssue = null): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media')->withUsage())
            ->extraAttributes(['class' => 'media-library-table'])
            ->columns([
                Stack::make([
                    ViewColumn::make('name')->label('Título')->view('filament.media.card')
                        ->viewData(fn (MediaAsset $record): array => [
                            'card' => static::card($record), 'selected' => $isSelected ? $isSelected($record) : null,
                            'selectionIssue' => $selectionIssue ? $selectionIssue($record) : null,
                        ])
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                            ->whereLike('name', '%'.$search.'%')->orWhereLike('alt_text', '%'.$search.'%')
                            ->orWhereHas('media', fn (Builder $query): Builder => $query->whereLike('file_name', '%'.$search.'%')
                                ->orWhereLike('custom_properties->original_filename', '%'.$search.'%'))))
                        ->sortable(),
                    TextColumn::make('created_at')->label('Fecha de carga')->sortable()->date('d/m/Y')->color('gray')
                        ->extraAttributes(['class' => 'media-card-date']),
                ])->space(0),
            ])
            ->contentGrid(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4, '2xl' => 5])
            ->recordUrl(null)->recordAction('preview')
            ->searchPlaceholder('Buscar imágenes…')->searchDebounce('400ms')
            ->paginationPageOptions([24, 48])->defaultPaginationPageOption(24)
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('format')->label('Formato')->options(['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WebP'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $mime): Builder => $query->whereHas('media', fn (Builder $query): Builder => $query->where('mime_type', $mime)))),
                SelectFilter::make('usage')->label('Uso')->options(['used' => 'En uso', 'unused' => 'Sin uso'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $usage): Builder => $query->whereUsed($usage === 'used'))),
                Filter::make('uploaded')->label('Fecha de carga')->schema([
                    DatePicker::make('from')->label('Desde')->native(false),
                    DatePicker::make('until')->label('Hasta')->native(false)->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        ! empty($data['from']) ? 'Desde '.$data['from'] : null,
                        ! empty($data['until']) ? 'Hasta '.$data['until'] : null,
                    ])),
            ])->deferFilters(false)
            ->emptyStateHeading('No hay imágenes para mostrar')
            ->emptyStateDescription('Sube tus primeras imágenes o limpia los filtros para ver la biblioteca.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateActions([
                Action::make('clearFilters')->label('Limpiar búsqueda y filtros')->color('gray')
                    ->action(function (Component $livewire): void {
                        $livewire->resetTableFiltersForm();
                        $livewire->resetTableSearch();
                    }),
            ])
            ->recordActions([
                Action::make('preview')->label('Vista previa')->icon(Heroicon::OutlinedEye)->authorize('view')
                    ->modalHeading(fn (MediaAsset $record): string => $record->name)->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (MediaAsset $record): View => view('filament.media.detail', ['detail' => static::detail($record)]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')->extraAttributes(['class' => 'media-preview-action']),
                Action::make('editMetadata')->label('Editar')->icon(Heroicon::OutlinedPencilSquare)->iconButton()->color('gray')->authorize('update')
                    ->modalHeading('Editar información de la imagen')->modalSubmitActionLabel('Guardar cambios')
                    ->fillForm(fn (MediaAsset $record): array => ['name' => $record->name, 'alt_text' => $record->alt_text])
                    ->schema([
                        TextInput::make('name')->label('Título')->required()->maxLength(255),
                        TextInput::make('alt_text')->label('Texto alternativo (ALT)')->required()->maxLength(255)
                            ->helperText('Describe lo que muestra la imagen para lectores de pantalla y buscadores.'),
                    ])->action(function (MediaAsset $record, array $data): void {
                        static::authorizeCurrentUser('update', $record);
                        $record->update(['name' => $data['name'], 'alt_text' => $data['alt_text']]);
                        Notification::make()->title('Información guardada')->success()->send();
                    }),
                Action::make('deleteImage')->label('Eliminar')->icon(Heroicon::OutlinedTrash)->iconButton()->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->hasPermission(Permission::DeleteMediaAsset) ?? false)
                    ->disabled(fn (MediaAsset $record): bool => $record->usageLabels() !== [])
                    ->tooltip(fn (MediaAsset $record): ?string => $record->usageLabels() !== [] ? 'La imagen está en uso. Retira sus referencias antes de eliminarla.' : null)
                    ->requiresConfirmation()->modalHeading('Eliminar imagen')->modalDescription('Se eliminarán el original y sus versiones. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Eliminar imagen')->action(function (MediaAsset $record): void {
                        static::authorizeCurrentUser('delete', $record);
                        $record->delete();
                        Notification::make()->title('Imagen eliminada')->success()->send();
                    }),
            ]);
    }

    /** @return array{url: ?string, format: string, dimensions: ?string, size: string, used: bool} */
    public static function card(MediaAsset $asset): array
    {
        $media = $asset->getFirstMedia('images');
        $url = static::thumbnailUrl($media);
        $dimensions = $media?->getCustomProperty('dimensions.original');

        return ['url' => $url, 'format' => $media ? strtoupper(str_replace('image/', '', $media->mime_type)) : 'Sin archivo',
            'dimensions' => isset($dimensions['width'], $dimensions['height']) ? $dimensions['width'].' × '.$dimensions['height'] : null,
            'size' => $media ? Number::fileSize($media->size, precision: 1) : '—', 'used' => $asset->usageLabels() !== []];
    }

    public static function thumbnailUrl(?Media $media): ?string
    {
        return $media && $media->hasGeneratedConversion('thumb')
            && Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot('thumb')) ? $media->getUrl('thumb') : null;
    }

    /** @return array<string, mixed> */
    public static function detail(MediaAsset $asset): array
    {
        static::authorizeCurrentUser('view', $asset);
        $media = $asset->getFirstMedia('images');
        $originalExists = $media && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot());

        return [...static::card($asset), 'preview_url' => MediaAsset::mediaUrl($media), 'original_url' => $originalExists ? $media->getUrl() : null,
            'original_missing' => ! $originalExists, 'name' => $asset->name, 'alt_text' => $asset->alt_text,
            'original_filename' => $media?->getCustomProperty('original_filename') ?: 'No disponible en este archivo histórico',
            'filename' => $media?->file_name ?: 'No disponible', 'created_at' => $asset->created_at?->format('d/m/Y H:i'),
            'usages' => $asset->usageLabels()];
    }

    private static function authorizeCurrentUser(string $ability, MediaAsset $asset): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user, 403);
        Gate::forUser($user)->authorize($ability, $asset);
    }
}
