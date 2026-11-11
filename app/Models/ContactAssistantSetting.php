<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

#[Fillable(['enabled', 'name', 'welcome_message', 'form_intro', 'destination_whatsapp', 'final_message', 'button_label', 'email_required', 'services_enabled', 'consent_text', 'privacy_path', 'field_schema', 'avatar_media_id'])]
#[Hidden(['destination_whatsapp'])]
class ContactAssistantSetting extends Model
{
    use HasFactory;

    protected $attributes = [
        'enabled' => false,
        'name' => 'Asistente de Contacto',
        'welcome_message' => 'Hola, cuéntanos cómo podemos ayudarte.',
        'form_intro' => 'Déjanos tus datos para continuar la consulta por WhatsApp.',
        'final_message' => 'Tu consulta se ha guardado. Continúa por WhatsApp para enviarnos el mensaje.',
        'button_label' => 'Contactar por WhatsApp',
        'email_required' => false,
        'services_enabled' => true,
        'consent_text' => 'Acepto el tratamiento de mis datos para atender esta consulta.',
        'privacy_path' => '/legal/privacy',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'email_required' => 'boolean', 'services_enabled' => 'boolean', 'field_schema' => 'array', 'avatar_media_id' => 'integer'];
    }

    public static function supportsConversation(): bool
    {
        return Schema::hasColumns('contact_assistant_settings', ['field_schema', 'avatar_media_id'])
            && Schema::hasColumn('contact_messages', 'assistant_answers');
    }

    public static function current(): self
    {
        return static::query()->find(1) ?? new static;
    }
}
