<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ToolPlatform: string implements HasLabel
{
    case Web = 'web';
    case Windows = 'windows';
    case Mac = 'mac';
    case Linux = 'linux';
    case Ios = 'ios';
    case Android = 'android';
    case Extension = 'extension';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Windows => 'Windows',
            self::Mac => 'macOS',
            self::Linux => 'Linux',
            self::Ios => 'iOS',
            self::Android => 'Android',
            self::Extension => 'Extensión de navegador',
            self::Api => 'API',
        };
    }
}
