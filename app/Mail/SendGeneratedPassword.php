<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SendGeneratedPassword extends Mailable
{
    use Queueable, SerializesModels;

    public string $firstName;
    public string $email;
    public string $password;

    public function __construct(string $firstName, string $email, string $password)
    {
        $this->firstName = $firstName;
        $this->email = $email;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Netania Account Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.generated_password',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
