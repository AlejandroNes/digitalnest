<?php

namespace App\Http\Requests;

use App\Models\ContactAssistantSetting;
use App\Rules\InternationalPhone;
use App\Services\ContactAssistant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class StoreContactAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(Schema::hasTable('contact_assistant_settings'), 404);
        $settings = ContactAssistantSetting::current();
        abort_unless($settings->enabled && InternationalPhone::normalize($settings->destination_whatsapp)
            && ($settings->field_schema === null || ContactAssistantSetting::supportsConversation()), 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $assistant = app(ContactAssistant::class);
        $settings = ContactAssistantSetting::current();
        $assistant->validateSubmissionToken($this->input('submission_token'), $settings);

        return $assistant->submissionRules($settings);
    }

    public function messages(): array
    {
        return ['website.prohibited' => 'No se pudo validar el formulario.'];
    }
}
