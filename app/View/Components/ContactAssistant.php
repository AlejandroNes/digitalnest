<?php

namespace App\View\Components;

use App\Services\ContactAssistant as Assistant;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ContactAssistant extends Component
{
    /** @var array<string, mixed> */
    public array $configuration;

    public function __construct(Assistant $assistant, ?array $configuration = null)
    {
        $this->configuration = $configuration ?? $assistant->configuration();
    }

    public function shouldRender(): bool
    {
        return $this->configuration['enabled'];
    }

    public function render(): View
    {
        return view('components.contact-assistant');
    }
}
