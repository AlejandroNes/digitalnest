<?php

namespace App\Filament\Support;

use App\Models\MediaAsset;
use App\Rules\MediaSelection;
use App\Services\MediaImageGuidelines;
use App\Services\MediaUploader;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MediaUploadAction
{
    public static function make(string $context = 'content'): Action
    {
        return Action::make('uploadImages')->label('Subir imágenes')->icon(Heroicon::OutlinedArrowUpTray)->databaseTransaction(false)
            ->authorize('create', MediaAsset::class)->modalHeading('Subir imágenes')->modalWidth(Width::ThreeExtraLarge)
            ->modalDescription('Selecciona las imágenes y completa su información antes de guardarlas en la biblioteca.')
            ->modalSubmitActionLabel('Guardar en la biblioteca')->modalCancelActionLabel('Cancelar')
            ->schema([
                FileUpload::make('images')->label('Imágenes')->multiple()->storeFiles(false)->preventFilePathTampering()
                    ->placeholder('Arrastra imágenes o <span class="filepond--label-action">selecciona archivos</span>')
                    ->image()->acceptedFileTypes(MediaSelection::mimeTypes($context))
                    ->maxSize(MediaSelection::maxKilobytes($context))->maxFiles(config('media-library.upload_limits.batch_files'))
                    ->maxParallelUploads(2)->required()->live()
                    ->helperText(MediaImageGuidelines::recommendation($context).' '.($context === 'favicon' ? 'PNG' : 'JPG, PNG o WebP').', máximo '.MediaSelection::maxKilobytes($context).' KB por imagen. Hasta '.config('media-library.upload_limits.batch_files').' imágenes y '.(config('media-library.upload_limits.batch_kb') / 1024).' MB por lote. Límite técnico: '.config('media-library.upload_limits.dimension').' px por lado y '.(config('media-library.upload_limits.pixels') / 1000000).' millones de píxeles.')
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                        $existing = collect($get('metadata') ?? [])->keyBy('upload_key');
                        $metadata = [];
                        foreach ((array) $state as $file) {
                            if (! $file instanceof UploadedFile) {
                                continue;
                            }
                            $key = $file->getFilename();
                            $metadata[] = $existing->get($key) ?? [
                                'upload_key' => $key,
                                'name' => Str::limit(str_replace(['_', '-'], ' ', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 255, ''),
                                'alt_text' => '',
                            ];
                        }
                        $set('metadata', $metadata);
                    }),
                Repeater::make('metadata')->label('Información de las imágenes')->defaultItems(0)->maxItems(config('media-library.upload_limits.batch_files'))
                    ->addable(false)->deletable(false)->reorderable(false)->columns(['default' => 1, 'sm' => 2])
                    ->visible(fn (Get $get): bool => count($get('images') ?? []) > 0)
                    ->itemLabel(fn (array $state): string => is_string($state['name'] ?? null) ? $state['name'] : 'Imagen')
                    ->schema([
                        Hidden::make('upload_key')->required()->rules(['string', 'max:255']),
                        TextInput::make('name')->label('Título')->required()->maxLength(255),
                        TextInput::make('alt_text')->label('Texto alternativo (ALT)')->required()->maxLength(255)
                            ->helperText('Describe lo que muestra esta imagen.'),
                    ]),
                View::make('filament.media.upload-status'),
            ])->action(function (array $data, Component $livewire, Schema $schema): void {
                $files = array_values($data['images']);
                $metadata = collect($data['metadata'] ?? [])->keyBy('upload_key');
                $items = [];
                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile || ! $metadata->has($file->getFilename())) {
                        throw ValidationException::withMessages([$schema->getStatePath().'.images' => 'Completa la información de cada imagen antes de guardar.']);
                    }
                    $information = $metadata->get($file->getFilename());
                    $items[] = ['image' => $file, 'name' => $information['name'], 'alt_text' => $information['alt_text']];
                }
                try {
                    $results = app(MediaUploader::class)->uploadBatch(auth()->user(), $items);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([$schema->getStatePath().'.images' => collect($exception->errors())->flatten()->all()]);
                }
                $livewire->mediaUploadResults = [];
                foreach ($results as $index => $result) {
                    $livewire->mediaUploadResults[] = [
                        'filename' => $files[$index]->getClientOriginalName(), 'status' => $result['status'],
                        'errors' => collect($result['errors'])->flatten()->all(),
                        'asset_id' => $result['asset']?->id,
                    ];
                }
                $failed = count(array_filter($results, fn (array $result): bool => $result['status'] === 'failed'));
                $notification = Notification::make()->title($failed ? 'Carga terminada con incidencias' : 'Imágenes guardadas')
                    ->body('Revisa el resultado de cada archivo en la biblioteca.');
                $failed ? $notification->warning()->send() : $notification->success()->send();
                $livewire->resetPage();
            });
    }
}
