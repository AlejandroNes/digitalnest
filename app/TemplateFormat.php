<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum TemplateFormat: string implements HasLabel
{
    case Text = 'text';
    case Markdown = 'markdown';
    case Json = 'json';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Markdown => 'Markdown',
            self::Json => 'JSON',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Text => 'txt',
            self::Markdown => 'md',
            self::Json => 'json',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Text => 'text/plain',
            self::Markdown => 'text/markdown',
            self::Json => 'application/json',
        };
    }
}
