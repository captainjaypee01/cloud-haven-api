<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailServiceInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class MailService implements MailServiceInterface
{
    /**
     * Send an email to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return void
     */
    public function send($to, Mailable $mailable): void
    {
        Mail::to($to)->send($mailable);
    }

    /**
     * Queue an email to be sent to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return void
     */
    public function queue($to, Mailable $mailable): void
    {
        Mail::to($to)->queue($mailable);
    }

    /**
     * Send an email later to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @return void
     */
    public function later($to, Mailable $mailable, $delay): void
    {
        Mail::to($to)->later($delay, $mailable);
    }
}
