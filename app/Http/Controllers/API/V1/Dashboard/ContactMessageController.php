<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\DTO\ContactMessageRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFormRequest;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Services\EmailTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class ContactMessageController extends Controller
{
    /**
     * Store a new contact message
     */
    public function store(ContactFormRequest $request)
    {
        $requestData = $request->validated();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        
        Log::info('Contact form submission received', [
            'email' => $requestData['email'],
            'name' => $requestData['name'],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'form_load_time' => $requestData['form_load_time'] ?? null,
        ]);

        try {
            // Create DTO and check for spam
            $dto = ContactMessageRequestDTO::from($requestData);
            
            // Check rate limiting
            $rateLimitResult = $this->checkRateLimits($requestData['email'], $ipAddress);
            if (!$rateLimitResult['allowed']) {
                Log::warning('Contact form rate limit exceeded', [
                    'email' => $requestData['email'],
                    'ip_address' => $ipAddress,
                    'reason' => $rateLimitResult['reason']
                ]);
                
                return new ErrorResponse(
                    'Too many submissions. Please try again later.',
                    JsonResponse::HTTP_TOO_MANY_REQUESTS
                );
            }

            // Check for spam
            $isSpam = $dto->isSpam();
            $spamReason = $isSpam ? $dto->getSpamReason() : null;

            // Create contact message record
            $contactMessage = ContactMessage::create([
                'name' => $requestData['name'],
                'email' => $requestData['email'],
                'message' => $requestData['message'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'is_spam' => $isSpam,
                'spam_reason' => $spamReason,
                'submitted_at' => now(),
            ]);

            Log::info('Contact message created', [
                'contact_message_id' => $contactMessage->id,
                'email' => $contactMessage->email,
                'name' => $contactMessage->name,
                'is_spam' => $isSpam,
                'spam_reason' => $spamReason,
            ]);

            // Send email to admin (only for non-spam messages)
            if (!$isSpam) {
                $this->sendNotificationEmail($contactMessage);
            } else {
                Log::info('Skipping email notification for spam message', [
                    'contact_message_id' => $contactMessage->id,
                    'spam_reason' => $spamReason
                ]);
            }

            return response()->json([
                'message' => 'Thank you for your message. We will get back to you soon!',
                'submitted_at' => $contactMessage->submitted_at->toISOString(),
            ], JsonResponse::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
                'ip_address' => $ipAddress,
            ]);

            return new ErrorResponse(
                'Unable to submit your message. Please try again.',
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Check rate limits for email and IP
     */
    private function checkRateLimits(string $email, string $ipAddress): array
    {
        // Check email rate limit (2 submissions per day)
        $emailCount = ContactMessage::recentByEmail($email, 24)->count();
        if ($emailCount >= 2) {
            return [
                'allowed' => false,
                'reason' => 'Email rate limit exceeded (2 per day)'
            ];
        }

        // Check IP rate limit (3 submissions per hour)
        $ipCount = ContactMessage::recentByIp($ipAddress, 1)->count();
        if ($ipCount >= 3) {
            return [
                'allowed' => false,
                'reason' => 'IP rate limit exceeded (3 per hour)'
            ];
        }

        // Check global rate limit (50 submissions per hour)
        $globalCount = ContactMessage::where('submitted_at', '>=', now()->subHour())->count();
        if ($globalCount >= 50) {
            return [
                'allowed' => false,
                'reason' => 'Global rate limit exceeded (50 per hour)'
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Send notification email to admin
     * 
     * The admin email is configurable via the MAIL_CONTACT_FORM_ADMIN_EMAIL
     * environment variable. Defaults to 'netaniadelaiya@gmail.com' if not set.
     */
    private function sendNotificationEmail(ContactMessage $contactMessage): void
    {
        try {
            $adminEmail = config('mail.contact_form_admin_email', 'netaniadelaiya@gmail.com');
            
            // Use EmailTrackingService for comprehensive logging
            EmailTrackingService::sendWithTracking(
                $adminEmail,
                new ContactMessageMail($contactMessage),
                'contact_form_notification',
                [
                    'contact_message_id' => $contactMessage->id,
                    'guest_name' => $contactMessage->name,
                    'guest_email' => $contactMessage->email,
                    'is_spam' => $contactMessage->is_spam,
                    'spam_reason' => $contactMessage->spam_reason,
                    'submitted_at' => $contactMessage->submitted_at->toISOString(),
                ]
            );
            
        } catch (\Exception $e) {
            // EmailTrackingService already logs failures, but we'll add additional context
            Log::error('Contact message notification email failed', [
                'contact_message_id' => $contactMessage->id,
                'admin_email' => config('mail.contact_form_admin_email', 'netaniadelaiya@gmail.com'),
                'error' => $e->getMessage(),
            ]);
            
            // Don't throw exception - we don't want email failures to break the form submission
        }
    }
}
