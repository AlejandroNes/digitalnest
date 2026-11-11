<?php

namespace App\Rules;

use Closure;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Contracts\Validation\ValidationRule;

class ScriptMarkup implements ValidationRule
{
    public const MAX_BYTES = 20000;

    public function __construct(public string $purpose = 'statistics', public string $position = 'head') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > self::MAX_BYTES || ! mb_check_encoding($value, 'UTF-8')) {
            $fail('El fragmento debe contener HTML válido y ocupar como máximo 20 KB.');

            return;
        }
        if (preg_match('/<\?(?:php|=)|-----BEGIN[^\r\n]*PRIVATE KEY-----/i', $value)) {
            $fail('No incluyas PHP, contraseñas ni claves privadas en este campo.');

            return;
        }
        if (preg_match('~googletagmanager\.com|google-analytics\.com|\bgtag\s*\(~i', $value)) {
            $fail('Configura Analytics y Tag Manager en sus campos específicos para evitar instalaciones duplicadas.');

            return;
        }
        if ($this->purpose === 'necessary' && $this->position !== 'head') {
            $fail('Las verificaciones sin seguimiento se insertan únicamente en el Head.');

            return;
        }
        $root = self::parse($value);
        if (! $root || ! $root->hasChildNodes() || ! $this->validChildren($root)) {
            $fail('Usa un fragmento con etiquetas permitidas, sin eventos HTML ni URLs inseguras. Las verificaciones solo admiten metaetiquetas name/content.');
        }
    }

    public static function parse(string $code): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<html><head><meta charset="UTF-8"></head><body><div id="integration-root">'.$code.'</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return $document->getElementById('integration-root');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function validChildren(DOMNode $parent): bool
    {
        $elements = 0;
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMComment) {
                continue;
            }
            if ($node instanceof DOMText) {
                if (trim($node->textContent) !== '') {
                    return false;
                }

                continue;
            }
            if (! $node instanceof DOMElement || ! $this->validElement($node)) {
                return false;
            }
            $elements++;
        }

        return $elements > 0;
    }

    private function validElement(DOMElement $node): bool
    {
        $tag = strtolower($node->tagName);
        if ($this->purpose === 'necessary') {
            return $tag === 'meta' && $node->attributes->length === 2 && $node->hasAttribute('name') && $node->hasAttribute('content')
                && preg_match('/^[A-Za-z0-9:_.-]*(?:verification|verify|validate)[A-Za-z0-9:_.-]*$/iD', $node->getAttribute('name'))
                && strtolower($node->getAttribute('name')) !== 'google-site-verification';
        }
        $allowed = $this->position === 'head' ? ['script', 'meta', 'link'] : ['script', 'noscript', 'iframe', 'img', 'div', 'span'];
        if (! in_array($tag, $allowed, true)) {
            return false;
        }
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'http-equiv', 'style'], true)) {
                return false;
            }
            if (in_array($name, ['src', 'href'], true) && ! self::safeUrl($attribute->value)) {
                return false;
            }
        }
        if ($tag === 'script' || $tag === 'iframe') {
            return true;
        }
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText || $child instanceof DOMComment) {
                    continue;
                }
                if (! $child instanceof DOMElement || ! $this->validElement($child)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function safeUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '..')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) && strtolower(parse_url($url, PHP_URL_SCHEME) ?? '') === 'https';
    }
}
