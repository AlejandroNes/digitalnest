<?php

namespace App\Services;

use App\LegalPageType;
use App\Models\ContactAssistantSetting;
use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\MediaAsset;
use App\Models\Service;
use App\Rules\AssistantPhone;
use App\Rules\InternationalPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ContactAssistant
{
    /** @return array<string, mixed> */
    public function configuration(?ContactAssistantSetting $settings = null, bool $preview = false): array
    {
        if (! Schema::hasTable('contact_assistant_settings')) {
            return ['enabled' => false];
        }
        $settings ??= ContactAssistantSetting::current();
        if (! $preview && (($settings->field_schema !== null && ! ContactAssistantSetting::supportsConversation()) || ! $settings->enabled || InternationalPhone::normalize($settings->destination_whatsapp) === null)) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'preview' => $preview,
            'field_schema' => ContactAssistantFields::fields($settings),
            'schema_revision' => $this->schemaRevision($settings),
            'avatar_url' => $this->avatarUrl($settings),
            'name' => $settings->name,
            'welcome_message' => $settings->welcome_message,
            'form_intro' => $settings->form_intro,
            'final_message' => $settings->final_message,
            'button_label' => $settings->button_label,
            'email_required' => $settings->email_required,
            'services_enabled' => $settings->services_enabled,
            'phone_countries' => PhoneCountries::options(),
            'default_phone_country' => PhoneCountries::defaultCountry(),
            'services' => $settings->services_enabled
                ? Service::query()->active()->ordered()->get(['id', 'name'])->toArray() : [],
            'consent_text' => $settings->consent_text,
            'privacy_url' => $this->privacyPath($settings) ? InternalUrl::absolute($this->privacyPath($settings)) : null,
            'submission_token' => $preview ? '' : ContactSubmissionToken::issue(ContactMessage::SOURCE_ASSISTANT, $this->consentContext($settings)),
        ];
    }

    public function privacyPath(ContactAssistantSetting $settings): ?string
    {
        $path = InternalUrl::path($settings->privacy_path);
        if (! $path || InternalUrl::protectedPath($path)) {
            return null;
        }
        if ($path === '/legal/privacy' && $this->privacyRevision() === 0) {
            return null;
        }

        return $path;
    }

    public function privacyRevision(): int
    {
        return LegalPage::query()->published()->where('type', LegalPageType::Privacy)->value('revision') ?? 0;
    }

    public function schemaRevision(ContactAssistantSetting $settings): string
    {
        return hash('sha256', json_encode(ContactAssistantFields::fields($settings), JSON_THROW_ON_ERROR));
    }

    public function avatarUrl(ContactAssistantSetting $settings): ?string
    {
        if (! $settings->avatar_media_id) {
            return null;
        }
        $media = Media::query()->where('model_type', (new MediaAsset)->getMorphClass())
            ->where('collection_name', 'images')->find($settings->avatar_media_id);
        if (! $media || ! $media->hasGeneratedConversion('contact-avatar')
            || ! Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot('contact-avatar'))) {
            return null;
        }

        return $media->getUrl('contact-avatar');
    }

    public function consentContext(ContactAssistantSetting $settings): string
    {
        $context = [
            $settings->consent_text, $this->privacyPath($settings), $this->privacyRevision(),
            $settings->email_required, $settings->services_enabled,
        ];
        if ($settings->field_schema !== null) {
            $context[] = $this->schemaRevision($settings);
        }

        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function submissionRules(ContactAssistantSetting $settings): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[\p{L}\p{M}][\p{L}\p{M} .\x{0027}\x{2019}\-]*[\p{L}\p{M}]$/u'],
            'whatsapp' => ['required', 'string', new AssistantPhone],
            'phone_country' => ['nullable', 'string', Rule::in(array_keys(PhoneCountries::metadata()))],
            'email' => [$settings->email_required ? 'required' : 'nullable', 'string', 'email:rfc', 'max:255'],
            'service_id' => $settings->services_enabled
                ? ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)] : ['prohibited'],
            'message' => ['nullable', 'string', 'max:1000', 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'consent' => ['required', 'accepted'],
            'website' => ['prohibited'],
            'submission_token' => ['required', 'string', 'max:2000'],
        ];
        $fields = ContactAssistantFields::fields($settings);
        $active = array_column($fields, 'id');
        foreach (['email', 'message'] as $optional) {
            if (! in_array($optional, $active, true)) {
                $rules[$optional] = ['prohibited'];
            }
        }
        foreach ($fields as $field) {
            if (in_array($field['id'], ['name', 'email', 'message'], true)) {
                $rules[$field['id']][] = $field['required'] ? 'required' : 'nullable';
                $rules[$field['id']][] = 'min:'.$field['min_length'];
                $rules[$field['id']][] = 'max:'.$field['max_length'];
            }
        }

        return [...$rules, ...ContactAssistantFields::answerRules($fields)];
    }

    /** @param array<string, mixed> $data */
    public function store(array $data): ContactMessage
    {
        return DB::transaction(function () use ($data): ContactMessage {
            $settings = ContactAssistantSetting::query()->lockForUpdate()->find(1);
            abort_unless($settings?->enabled && InternationalPhone::normalize($settings->destination_whatsapp)
                && ($settings->field_schema === null || ContactAssistantSetting::supportsConversation()), 404);
            $key = $this->validateSubmissionToken($data['submission_token'] ?? null, $settings);
            $data = Validator::make($data, $this->submissionRules($settings), ['website.prohibited' => 'No se pudo validar el formulario.'])->validate();
            $service = null;
            if ($data['service_id'] ?? null) {
                $service = $settings->services_enabled
                    ? Service::query()->active()->lockForUpdate()->find($data['service_id']) : null;
                if (! $service) {
                    throw ValidationException::withMessages(['service_id' => 'El servicio ya no está disponible.']);
                }
            }

            $snapshot = ContactAssistantFields::snapshot(ContactAssistantFields::fields($settings), $data, $service?->name);
            $total = array_sum(array_map(fn (array $answer): int => mb_strlen($answer['display_value']), $snapshot));
            if ($total > 4000) {
                throw ValidationException::withMessages(['answers' => 'La consulta es demasiado larga. Reduce las respuestas a un total de 4.000 caracteres.']);
            }

            return ContactMessage::query()->firstOrCreate(['submission_key' => $key], [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => PhoneCountries::normalize($data['whatsapp'], $data['phone_country'] ?? null),
                'message' => $data['message'] ?? '',
                'source' => ContactMessage::SOURCE_ASSISTANT,
                'service_id' => $service?->id,
                'service_name' => $service?->name,
                'consented_at' => now(),
                'consent_text' => $settings->consent_text,
                'privacy_path' => $this->privacyPath($settings),
                'privacy_revision' => $this->privacyRevision(),
                'whatsapp_status' => ContactMessage::WHATSAPP_PREPARED,
                ...(ContactAssistantSetting::supportsConversation() ? ['assistant_answers' => $snapshot] : []),
            ]);
        });
    }

    public function validateSubmissionToken(mixed $token, ContactAssistantSetting $settings): string
    {
        $key = is_string($token) && strlen($token) <= 2000
            ? ContactSubmissionToken::key($token, ContactMessage::SOURCE_ASSISTANT, $this->consentContext($settings)) : null;
        if ($key === null) {
            throw ValidationException::withMessages(['submission_token' => 'El formulario ha cambiado o caducado. Revisa la conversación actualizada y vuelve a aceptar el consentimiento.']);
        }

        return $key;
    }

    public function whatsappUrl(ContactMessage $message): string
    {
        $destination = InternationalPhone::normalize(ContactAssistantSetting::current()->destination_whatsapp);
        abort_unless($destination, 404);
        $lines = ['Hola, quiero solicitar información.', '', '*Nombre:* '.$message->name, '*WhatsApp:* '.$message->phone];
        if ($message->email) {
            $lines[] = '*Correo:* '.$message->email;
        }
        if ($message->service_name) {
            $lines[] = '*Servicio:* '.$message->service_name;
        }
        if ($message->message !== '') {
            $lines[] = '*Mensaje:* '.$message->message;
        }

        foreach ($message->assistant_answers ?? [] as $answer) {
            if (! isset(ContactAssistantFields::BUILTINS[$answer['id']]) && $answer['display_value'] !== '') {
                $lines[] = '*'.$answer['label'].':* '.$answer['display_value'];
            }
        }

        return 'https://wa.me/'.substr($destination, 1).'?'.http_build_query(['text' => implode("\n", $lines)], '', '&', PHP_QUERY_RFC3986);
    }
}
