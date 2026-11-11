<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ContactSubmissionToken
{
    public static function issue(string $purpose = 'contact', ?string $context = null): string
    {
        return Crypt::encryptString(json_encode([
            'key' => (string) Str::uuid(), 'issued_at' => now()->timestamp,
            'purpose' => $purpose, 'context' => $context,
        ], JSON_THROW_ON_ERROR));
    }

    public static function key(string $token, string $purpose = 'contact', ?string $context = null): ?string
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($payload) || ! is_string($payload['key'] ?? null) || ! Str::isUuid($payload['key'])
            || ! is_int($payload['issued_at'] ?? null) || $payload['issued_at'] > now()->timestamp
            || $payload['issued_at'] < now()->subMinutes(config('contact.token_lifetime_minutes'))->timestamp
            || ($payload['purpose'] ?? 'contact') !== $purpose || ($payload['context'] ?? null) !== $context) {
            return null;
        }

        return $payload['key'];
    }
}
