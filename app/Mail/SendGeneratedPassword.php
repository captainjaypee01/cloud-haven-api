<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

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

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        $appUrl = config('app.url', 'https://netaniadelaiya.com');
        $domain = parse_url($appUrl, PHP_URL_HOST) ?: 'netaniadelaiya.com';
        $uniqueId = Str::uuid()->toString();
        
        return new Headers(
            messageId: sprintf('password-generated-%s@%s', $uniqueId, $domain),
            text: [
                'X-Mailer' => 'Netania De Laiya Reservation System',
                'Precedence' => 'bulk',
                'List-Unsubscribe' => config('app.url', 'https://netaniadelaiya.com') . '/unsubscribe',
                'X-Auto-Response-Suppress' => 'All',
            ],
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
