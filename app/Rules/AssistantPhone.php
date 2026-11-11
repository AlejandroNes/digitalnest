<?php

namespace App\Rules;

use App\Services\PhoneCountries;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class AssistantPhone implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (PhoneCountries::normalize($value, $this->data['phone_country'] ?? null) === null) {
            $fail('Revisa el número y el país seleccionado. Usa un número nacional completo o un número internacional con +.');
        }
    }
}
