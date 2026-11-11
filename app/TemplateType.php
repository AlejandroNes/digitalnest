<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum TemplateType: string implements HasLabel
{
    case Prompt = 'prompt';
    case SystemInstructions = 'system_instructions';
    case Skill = 'skill';
    case Assistant = 'assistant';
    case Automation = 'automation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prompt => 'Prompt',
            self::SystemInstructions => 'Instrucciones de sistema',
            self::Skill => 'Skill',
            self::Assistant => 'Asistente / GPT',
            self::Automation => 'Automatización',
            self::Other => 'Otro',
        };
    }
}
