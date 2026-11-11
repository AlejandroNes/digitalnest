<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Services\MediaUploader;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected ?bool $hasDatabaseTransactions = false;

    private bool $reusedImage = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $result = app(MediaUploader::class)->upload(auth()->user(), $data['image'], $data['name'], $data['alt_text']);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$this->form->getStatePath().'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        $this->reusedImage = ! $result['created'];

        return $result['asset'];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->reusedImage ? 'Esta imagen ya existe en la biblioteca' : 'Imagen guardada';
    }
}
