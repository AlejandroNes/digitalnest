<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    public function getSubheading(): ?string
    {
        return 'Revisa las consultas pendientes y responde al visitante desde tu correo habitual. Marcar como leído conserva el mensaje y no confirma una respuesta. Si no llega un correo, revisa esta bandeja: no hay reenvíos automáticos. Elimina solo las consultas que ya no necesites.';
    }

    protected static string $resource = ContactMessageResource::class;
}
