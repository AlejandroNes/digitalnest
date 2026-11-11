<?php

namespace App\Http\Requests;

use App\Services\InternalUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCookieConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'reject', 'save'])],
            'statistics' => ['sometimes', 'boolean'],
            'marketing' => ['sometimes', 'boolean'],
            'preferences' => ['sometimes', 'boolean'],
            'return_to' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! InternalUrl::path($value) || InternalUrl::protectedPath($value)) {
                    $fail('La ruta de retorno debe ser una página pública interna.');
                }
            }],
        ];
    }
}
