<?php

namespace App\Models;

use App\ContactMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['submission_key', 'name', 'email', 'phone', 'subject', 'message', 'source', 'service_id', 'service_name', 'consented_at', 'consent_text', 'privacy_path', 'privacy_revision', 'whatsapp_status', 'assistant_answers'])]
#[Hidden(['submission_key'])]
class ContactMessage extends Model
{
    use HasFactory;

    public const SOURCE_FORM = 'contact_form';

    public const SOURCE_ASSISTANT = 'contact_assistant';

    public const WHATSAPP_PREPARED = 'link_prepared';

    protected $attributes = ['status' => 'pending', 'source' => self::SOURCE_FORM];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'privacy_revision' => 'integer', 'status' => ContactMessageStatus::class, 'assistant_answers' => 'array', 'notification_attempted_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $message): void {
            if ($message->isDirty(['submission_key', 'name', 'email', 'phone', 'subject', 'message', 'source', 'service_id', 'service_name', 'consented_at', 'consent_text', 'privacy_path', 'privacy_revision', 'whatsapp_status', 'assistant_answers'])) {
                throw ValidationException::withMessages(['message' => 'El contenido original no se puede modificar.']);
            }
        });
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', ContactMessageStatus::Pending);
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [self::SOURCE_FORM => 'Formulario de contacto', self::SOURCE_ASSISTANT => 'Asistente de Contacto'];
    }

    /** @return array<string, string> */
    public static function notificationLabels(): array
    {
        return [
            'unattempted' => 'Sin intento registrado',
            'unconfirmed' => 'Intentada, sin aceptación confirmada',
            'accepted' => 'Aceptada por el servidor',
        ];
    }

    public function notificationStatus(): string
    {
        return $this->notified_at ? 'accepted' : ($this->notification_attempted_at ? 'unconfirmed' : 'unattempted');
    }

    public function notificationLabel(): string
    {
        return self::notificationLabels()[$this->notificationStatus()];
    }

    public function markAs(ContactMessageStatus $status): void
    {
        $this->status = $status;
        $this->save();
    }
}
