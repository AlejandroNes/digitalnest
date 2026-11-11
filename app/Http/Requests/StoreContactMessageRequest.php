<?php

namespace App\Http\Requests;

use App\Services\ContactSubmissionToken;
use App\Services\InternalUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContactMessageRequest extends FormRequest
{
    private string $submissionKey;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150', 'not_regex:/[<>\r\n\x00-\x1F\x7F]/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9][0-9 ()\-\.]{5,38}$/D'],
            'subject' => ['nullable', 'string', 'max:200', 'not_regex:/[<>\r\n\x00-\x1F\x7F]/u'],
            'message' => ['required', 'string', 'min:10', 'max:10000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'website' => ['prohibited'],
            'submission_token' => ['required', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! InternalUrl::path($value) || InternalUrl::protectedPath($value)) {
                    $fail('La ruta de retorno no es válida.');
                }
            }],
        ];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('submission_token')) {
                return;
            }
            $key = ContactSubmissionToken::key($this->input('submission_token'));
            if ($key === null) {
                $validator->errors()->add('submission_token', 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.');

                return;
            }
            $this->submissionKey = $key;
        }];
    }

    public function submissionKey(): string
    {
        return $this->submissionKey;
    }

    public function messages(): array
    {
        return ['website.prohibited' => 'No se pudo validar el formulario.'];
    }
}
