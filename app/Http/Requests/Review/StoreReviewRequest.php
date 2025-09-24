<?php

namespace App\Http\Requests\Review;

use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class StoreReviewRequest extends FormRequest
{
    public function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $response = new ValidationErrorResponse(
            "Validation Errors",
            $errors,
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY
        );

        $json = $response->toResponse($this);
        throw new HttpResponseException($json);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (is_null($this->user())) return false;

        return in_array($this->user()->role, config('roles.admin_roles'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'booking_id' => 'nullable|integer|exists:bookings,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'type' => ['required', 'string', Rule::in(['room', 'resort'])],
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
            'is_testimonial' => 'sometimes|boolean',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages()
    {
        return [
            'type.in' => 'Invalid type. Valid values: room, resort',
            'rating.min' => 'Rating must be at least 1',
            'rating.max' => 'Rating must be at most 5',
            'comment.max' => 'Comment must not exceed 1000 characters',
        ];
    }
}
