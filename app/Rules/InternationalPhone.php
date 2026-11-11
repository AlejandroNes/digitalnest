<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InternationalPhone implements ValidationRule
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 40 || ! preg_match('/^(?:\+|00)[0-9 ()\.\-]+$/D', $value)) {
            return null;
        }
        $number = preg_replace('/[ ()\.\-]/', '', $value);
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }

        return preg_match('/^\+[1-9][0-9]{7,14}$/D', $number) ? $number : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalize($value) === null) {
            $fail('Introduce un número internacional con + o 00 y código de país, de 8 a 15 dígitos.');
        }
    }
}
