<?php

namespace App\Mail;

use App\Models\GeneralSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMessage extends Mailable
{
    /** @param array{address: string, name: string} $sender */
    public function __construct(public GeneralSetting $identity, public array $sender) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->sender['address'], $this->sender['name']), subject: 'Prueba de configuración SMTP');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.smtp-test', text: 'mail.smtp-test-text');
    }
}
