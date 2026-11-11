<?php

namespace App\View\Components;

use App\Services\ContactSubmissionToken;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ContactForm extends Component
{
    public function submissionToken(): string
    {
        return ContactSubmissionToken::issue();
    }

    public function render(): View
    {
        return view('components.contact-form');
    }
}
