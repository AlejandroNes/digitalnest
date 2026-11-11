<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Validator;

class ContactMessageReceived extends Mailable
{
    /** @param array{address: string, name: string} $sender */
    public function __construct(public ContactMessage $contact, public GeneralSetting $identity, public array $sender) {}

    public function envelope(): Envelope
    {
        Validator::make(['email' => $this->contact->email], ['email' => 'required|email:rfc|max:255'])->validate();

        return new Envelope(replyTo: [new Address($this->contact->email)], from: new Address($this->sender['address'], $this->sender['name']), subject: 'Nueva consulta de contacto');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-message', text: 'mail.contact-message-text');
    }
}
