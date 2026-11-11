<?php

namespace App\Services;

use App\Rules\InternationalPhone;
use Locale;

class PhoneCountries
{
    /** @return array<string, array{dial_code: string, national_prefix: string, lengths: list<int>, example: string, main: bool}> */
    public static function metadata(): array
    {
        return config('phone-countries', []);
    }

    /** @return list<array{code: string, name: string, flag: string, dial_code: string, national_prefix: string, lengths: list<int>, example: string, main: bool}> */
    public static function options(): array
    {
        $countries = [];
        foreach (self::metadata() as $code => $metadata) {
            $countries[] = [
                'code' => $code,
                'name' => Locale::getDisplayRegion('und_'.$code, 'es'),
                'flag' => mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1])),
                ...$metadata,
            ];
        }
        usort($countries, fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $countries;
    }

    public static function defaultCountry(): string
    {
        $code = strtoupper((string) config('contact.assistant_default_country', 'BO'));

        return isset(self::metadata()[$code]) ? $code : 'BO';
    }

    public static function normalize(mixed $value, mixed $country = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($country === null || $country === '') {
            return InternationalPhone::normalize($value);
        }
        $metadata = is_string($country) ? self::metadata()[$country] ?? null : null;
        if (! $metadata || strlen($value) > 40 || ! preg_match('/^(?:\+|00)?[0-9 ().-]+$/D', $value)) {
            return null;
        }
        $digits = preg_replace('/[ ().-]/', '', $value);
        if (str_starts_with($digits, '+') || str_starts_with($digits, '00')) {
            $international = InternationalPhone::normalize($value);
            if (! $international || ! str_starts_with($international, '+'.$metadata['dial_code'])) {
                return null;
            }
            $digits = substr($international, strlen($metadata['dial_code']) + 1);
        }
        $prefix = $metadata['national_prefix'];
        if (! in_array(strlen($digits), $metadata['lengths'], true) && $prefix !== '' && str_starts_with($digits, $prefix)) {
            $digits = substr($digits, strlen($prefix));
        }
        if (! in_array(strlen($digits), $metadata['lengths'], true)) {
            return null;
        }

        return InternationalPhone::normalize('+'.$metadata['dial_code'].$digits);
    }
}
