<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendBookingEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_type' => ['required', 'string', Rule::in(['reservation', 'confirmation'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email_type.required' => 'Please select which email to resend.',
            'email_type.in' => 'Email type must be reservation or confirmation.',
        ];
    }
}
