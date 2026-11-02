<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $destination,
        public array $fields,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->senderEmail !== '' ? [new Address($this->senderEmail, $this->senderName)] : [],
            subject: 'Contato: '.$this->destination,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.contact-message',
        );
    }
}
