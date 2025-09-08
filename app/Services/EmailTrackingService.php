<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTrackingService
{
    /**
     * Log email sending attempt
     */
    public static function logEmailSent(string $emailType, string $recipient, array $context = []): void
    {
        Log::info('Email sent', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'sent_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log email queuing
     */
    public static function logEmailQueued(string $emailType, string $recipient, array $context = []): void
    {
        Log::info('Email queued', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'queued_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log email delivery failure
     */
    public static function logEmailFailed(string $emailType, string $recipient, string $error, array $context = []): void
    {
        Log::error('Email delivery failed', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'error' => $error,
            'failed_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log email bounce
     */
    public static function logEmailBounced(string $emailType, string $recipient, string $bounceReason, array $context = []): void
    {
        Log::warning('Email bounced', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'bounce_reason' => $bounceReason,
            'bounced_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log email opened (if tracking is implemented)
     */
    public static function logEmailOpened(string $emailType, string $recipient, array $context = []): void
    {
        Log::info('Email opened', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'opened_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log email clicked (if tracking is implemented)
     */
    public static function logEmailClicked(string $emailType, string $recipient, string $linkUrl, array $context = []): void
    {
        Log::info('Email link clicked', array_merge([
            'email_type' => $emailType,
            'recipient' => $recipient,
            'link_url' => $linkUrl,
            'clicked_at' => now()->toISOString(),
        ], $context));
    }

    /**
     * Send email with comprehensive tracking
     */
    public static function sendWithTracking(string $recipient, $mailable, string $emailType, array $context = []): void
    {
        try {
            // Log email queuing
            self::logEmailQueued($emailType, $recipient, $context);
            
            // Send the email
            Mail::to($recipient)->queue($mailable);
            
            // Log successful queuing
            Log::info('Email queued successfully', array_merge([
                'email_type' => $emailType,
                'recipient' => $recipient,
                'queued_at' => now()->toISOString(),
            ], $context));
            
        } catch (\Exception $e) {
            // Log email failure
            self::logEmailFailed($emailType, $recipient, $e->getMessage(), $context);
            throw $e;
        }
    }

    /**
     * Send email immediately with tracking
     */
    public static function sendNowWithTracking(string $recipient, $mailable, string $emailType, array $context = []): void
    {
        try {
            // Log email sending attempt
            self::logEmailSent($emailType, $recipient, $context);
            
            // Send the email immediately
            Mail::to($recipient)->send($mailable);
            
            // Log successful sending
            Log::info('Email sent successfully', array_merge([
                'email_type' => $emailType,
                'recipient' => $recipient,
                'sent_at' => now()->toISOString(),
            ], $context));
            
        } catch (\Exception $e) {
            // Log email failure
            self::logEmailFailed($emailType, $recipient, $e->getMessage(), $context);
            throw $e;
        }
    }
}
