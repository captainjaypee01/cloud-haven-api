<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class ContactMessageRequestDTO extends Data
{
    /**
     * @param string $name
     * @param string $email
     * @param string $message
     * @param string|null $honeypot
     * @param int|null $form_load_time
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $message,
        public ?string $honeypot = null,
        public ?int $form_load_time = null
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'honeypot' => ['nullable', 'string', 'max:0'], // Should be empty
            'form_load_time' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Check if this is likely a spam submission
     */
    public function isSpam(): bool
    {
        // Check honeypot field
        if (!empty($this->honeypot)) {
            return true;
        }

        // Check if form was submitted too quickly (less than 5 seconds)
        if ($this->form_load_time && $this->form_load_time < 5) {
            return true;
        }

        // Check for suspicious keywords
        $suspiciousKeywords = [
            'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner',
            'congratulations', 'free money', 'click here', 'buy now',
            'act now', 'limited time', 'guaranteed', 'no risk',
            'make money', 'work from home', 'get rich', 'investment',
            'loan', 'credit', 'debt', 'mortgage', 'insurance'
        ];

        $messageText = strtolower($this->message);
        foreach ($suspiciousKeywords as $keyword) {
            if (strpos($messageText, $keyword) !== false) {
                return true;
            }
        }

        // Check for excessive repetition
        $words = explode(' ', $messageText);
        $wordCounts = array_count_values($words);
        foreach ($wordCounts as $count) {
            if ($count > 5) { // Same word repeated more than 5 times
                return true;
            }
        }

        return false;
    }

    /**
     * Get spam reason if detected
     */
    public function getSpamReason(): ?string
    {
        if (!empty($this->honeypot)) {
            return 'Honeypot field filled';
        }

        if ($this->form_load_time && $this->form_load_time < 5) {
            return 'Form submitted too quickly';
        }

        $suspiciousKeywords = [
            'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner',
            'congratulations', 'free money', 'click here', 'buy now',
            'act now', 'limited time', 'guaranteed', 'no risk',
            'make money', 'work from home', 'get rich', 'investment',
            'loan', 'credit', 'debt', 'mortgage', 'insurance'
        ];

        $messageText = strtolower($this->message);
        foreach ($suspiciousKeywords as $keyword) {
            if (strpos($messageText, $keyword) !== false) {
                return "Suspicious keyword detected: {$keyword}";
            }
        }

        $words = explode(' ', $messageText);
        $wordCounts = array_count_values($words);
        foreach ($wordCounts as $word => $count) {
            if ($count > 5) {
                return "Excessive repetition of word: {$word}";
            }
        }

        return null;
    }
}
