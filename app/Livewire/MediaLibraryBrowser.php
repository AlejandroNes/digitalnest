<?php

namespace App\Livewire;

use App\Filament\Support\MediaLibraryTable;
use App\Filament\Support\MediaUploadAction;
use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\MediaSelection;
use App\Services\MediaImageGuidelines;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryBrowser extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable, RestrictsFileUploadsToSchemaComponents, WithoutUrlPagination;

    public const MAX_SELECTION = 50;

    #[Locked]
    public string $target = '';

    #[Locked]
    public string $context = 'content';

    #[Locked]
    public ?int $selectedMediaId = null;

    #[Locked]
    public bool $multiple = false;

    /** @var list<int> */
    #[Locked]
    public array $selectedMediaIds = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $mediaUploadResults = [];

    /** @var array<int, string|null> */
    private array $selectionIssues = [];

    public function mount(string $target, string $context = 'content', ?int $initialMediaId = null, bool $multiple = false): void
    {
        $this->authorizeBrowser();
        abort_unless(in_array($context, MediaImageGuidelines::contexts(), true), 403);
        abort_if($multiple && $context !== 'gallery', 403);
        $this->target = $target;
        $this->context = $context;
        $this->selectedMediaId = $initialMediaId;
        $this->multiple = $multiple;
    }

    public function table(Table $table): Table
    {
        MediaLibraryTable::configure($table->query(MediaAsset::query()), fn (MediaAsset $record): bool => $this->isSelected($record), fn (MediaAsset $record): ?string => $this->selectionIssue($record));
        $preview = $table->getAction('preview')->extraAttributes(['class' => 'media-picker-preview']);

        return $table->toolbarActions([MediaUploadAction::make($this->context)])
            ->recordAction('selectImage')->recordClasses(fn (MediaAsset $record): ?string => $this->isSelected($record) ? 'media-picker-selected' : null)
            ->recordActions([
                Action::make('selectImage')->label(fn (MediaAsset $record): string => $this->isSelected($record) ? ($this->multiple ? 'Quitar' : 'Seleccionada') : 'Seleccionar')->icon('heroicon-o-check')->color('primary')
                    ->disabled(fn (MediaAsset $record): bool => $this->selectionIssue($record) !== null && ! $this->isSelected($record))
                    ->tooltip(fn (MediaAsset $record): ?string => $this->selectionIssue($record))
                    ->extraAttributes(fn (MediaAsset $record): array => ['aria-pressed' => $this->isSelected($record) ? 'true' : 'false'])
                    ->action(function (MediaAsset $record): void {
                        $this->selectMedia($record->id);
                    }),
                $preview,
            ]);
    }

    public function render(): View
    {
        $this->authorizeBrowser();

        $ids = $this->multiple ? $this->selectedMediaIds : array_filter([$this->selectedMediaId]);
        $images = Media::query()->with('model')->where('model_type', (new MediaAsset)->getMorphClass())
            ->where('collection_name', 'images')->whereIn('id', $ids)->get()->keyBy('id');

        return view('filament.media.browser', [
            'guidelines' => MediaImageGuidelines::forContext($this->context),
            'allowedFormats' => $this->context === 'favicon' ? 'PNG' : 'JPG, PNG o WebP',
            'maxKilobytes' => MediaSelection::maxKilobytes($this->context),
            'selectedImages' => collect($ids)->map(fn (int $id): ?Media => $images->get($id))->filter(),
        ]);
    }

    public function removeSelection(int $mediaId): void
    {
        $this->authorizeBrowser();
        abort_unless($this->multiple, 403);
        $this->selectedMediaIds = array_values(array_filter($this->selectedMediaIds, fn (int $id): bool => $id !== $mediaId));
        $this->dispatch('media-library-selection', target: $this->target, mediaIds: $this->selectedMediaIds);
        $this->resetValidation('image');
    }

    public function clearSelection(): void
    {
        $this->authorizeBrowser();
        $this->selectedMediaId = null;
        $this->selectedMediaIds = [];
        $this->resetValidation('image');
        $this->multiple
            ? $this->dispatch('media-library-selection', target: $this->target, mediaIds: [])
            : $this->dispatch('media-library-selection', target: $this->target, mediaId: null);
    }

    public function selectMedia(int $assetId): void
    {
        $this->authorizeBrowser();
        $record = MediaAsset::query()->with('media')->findOrFail($assetId);
        $id = $record->getFirstMedia('images')?->id;
        if ($this->multiple && in_array($id, $this->selectedMediaIds, true)) {
            $this->removeSelection($id);

            return;
        }
        Validator::make(['image' => $id], ['image' => ['required', new MediaSelection($this->context)]])->validate();
        if ($this->multiple) {
            if (count($this->selectedMediaIds) >= self::MAX_SELECTION) {
                throw ValidationException::withMessages(['image' => 'Puedes elegir hasta '.self::MAX_SELECTION.' imágenes por selección.']);
            }
            $this->selectedMediaIds[] = $id;
            $this->dispatch('media-library-selection', target: $this->target, mediaIds: $this->selectedMediaIds);
        } else {
            $this->selectedMediaId = $id;
            $this->dispatch('media-library-selection', target: $this->target, mediaId: $id);
        }
        $this->resetValidation('image');
    }

    private function selectionIssue(MediaAsset $record): ?string
    {
        if (array_key_exists($record->id, $this->selectionIssues)) {
            return $this->selectionIssues[$record->id];
        }
        $media = $record->getFirstMedia('images');
        $issue = match (true) {
            ! $media => 'Este medio no tiene un archivo disponible.',
            ! in_array($media->mime_type, MediaSelection::mimeTypes($this->context), true) => $this->context === 'favicon' ? 'Este campo requiere una imagen PNG.' : 'Utiliza una imagen JPG, PNG o WebP.',
            $media->size > MediaSelection::maxKilobytes($this->context) * 1024 => 'Supera el tamaño permitido para este campo.',
            ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot()) => 'El archivo original no está disponible.',
            default => null,
        };

        return $this->selectionIssues[$record->id] = $issue;
    }

    private function isSelected(MediaAsset $record): bool
    {
        $id = $record->getFirstMedia('images')?->id;

        return $id !== null && ($this->multiple ? in_array($id, $this->selectedMediaIds, true) : $id === $this->selectedMediaId);
    }

    private function authorizeBrowser(): void
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user && Gate::forUser($user)->allows('viewAny', MediaAsset::class), 403);
    }
}
