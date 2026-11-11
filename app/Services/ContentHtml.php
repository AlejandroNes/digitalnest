<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ContentHtml
{
    public static function sanitize(?string $html): string
    {
        $config = new HtmlSanitizerConfig;
        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'hr'] as $element) {
            $config = $config->allowElement($element);
        }
        $config = $config->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()->withMaxInputLength(50000);

        return (new HtmlSanitizer($config))->sanitize($html ?? '');
    }
}
