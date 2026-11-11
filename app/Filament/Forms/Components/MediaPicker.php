<?php

namespace App\Filament\Forms\Components;

use App\Livewire\MediaLibraryBrowser;
use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\MediaSelection;
use App\Services\MediaImageGuidelines;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPicker extends Field
{
    protected string $view = 'filament.forms.components.media-picker';

    protected string $imageContext = 'content';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules(fn (): array => [new MediaSelection($this->imageContext)]);
        $this->registerActions([
            fn (MediaPicker $component): Action => $component->chooseImageAction(),
            fn (MediaPicker $component): Action => $component->clearImageAction(),
        ]);
    }

    private function chooseImageAction(): Action
    {
        return Action::make('chooseImage')->label('Elegir imagen')->icon('heroicon-o-photo')
            ->disabled(fn (): bool => $this->isDisabled())->authorize(fn (): bool => $this->canBrowse())->modalHeading('Biblioteca multimedia')
            ->modalWidth(Width::SevenExtraLarge)->stickyModalHeader()->stickyModalFooter()
            ->modalSubmitActionLabel('Seleccionar imagen')->modalCancelActionLabel('Cancelar')
            ->fillForm(fn (): array => ['selected_media_id' => $this->getState()])
            ->schema(fn (): array => [
                Hidden::make('selected_media_id')->required()->rules([new MediaSelection($this->imageContext)]),
                View::make('filament.media.selection-bridge')->viewData(['target' => $this->selectionTarget()]),
                Livewire::make(MediaLibraryBrowser::class, [
                    'target' => $this->selectionTarget(), 'context' => $this->imageContext,
                    'initialMediaId' => $this->getState() ? (int) $this->getState() : null,
                ])->key($this->selectionTarget()),
            ])->action(function (array $data): void {
                $this->state((int) $data['selected_media_id']);
                $this->callAfterStateUpdated();
            });
    }

    private function clearImageAction(): Action
    {
        return Action::make('clearImage')->label('Retirar imagen')->color('gray')->icon('heroicon-o-x-mark')
            ->disabled(fn (): bool => $this->isDisabled())->visible(fn (): bool => ! $this->isRequired() && filled($this->getState()))
            ->authorize(fn (): bool => $this->canBrowse())->action(function (): void {
                $this->state(null);
                $this->callAfterStateUpdated();
            });
    }

    public function imageContext(string $context): static
    {
        $this->imageContext = $context;

        return $this;
    }

    public function selectionTarget(): string
    {
        return $this->getLivewire()->getId().':'.$this->getStatePath();
    }

    /** @return array{label: string, width: int, height: int, ratio: string, crop: bool, description: string} */
    public function imageGuidelines(): array
    {
        return MediaImageGuidelines::forContext($this->imageContext);
    }

    public function selectedMedia(): ?Media
    {
        if (! $this->canBrowse() || ! is_scalar($this->getState())) {
            return null;
        }

        return Media::query()->with('model')->where('model_type', (new MediaAsset)->getMorphClass())
            ->where('collection_name', 'images')->find($this->getState());
    }

    private function canBrowse(): bool
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;

        return $user && Gate::forUser($user)->allows('viewAny', MediaAsset::class);
    }
}
