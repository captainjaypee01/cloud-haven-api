<?php

namespace App\Contracts\Mail;

use Illuminate\Mail\Mailable;

interface MailServiceInterface
{
    /**
     * Send an email to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return void
     */
    public function send($to, Mailable $mailable): void;

    /**
     * Queue an email to be sent to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return void
     */
    public function queue($to, Mailable $mailable): void;

    /**
     * Send an email later to the specified recipients.
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @return void
     */
    public function later($to, Mailable $mailable, $delay): void;
}
