<?php

namespace App\Http\Requests\User;

use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\JsonResponse;

class StoreUserRequest extends FormRequest
{
    public function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        // throw new ValidationErrorResponse("Validation Errors", $validator->errors()->toArray());
        // Build your custom response object
        $response = new ValidationErrorResponse(
            "Validation Errors",
            $errors,
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY
        );

        // Convert it to a JsonResponse
        $json = $response->toResponse($this);

        // Throw as an exception so Laravel returns it immediately
        throw new HttpResponseException($json);
    }
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (is_null($this->user())) return false;

        return in_array($this->user()->role, config('roles.superadmin_roles'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clerk_id' => ['nullable'],
            'role'  => ['required', 'string'],
            'email' => ['required', 'string'],
            // 'email_addresses.0.email_address' => ['required','email'],
            // 'email_addresses.0.linked_to' => ['array'],
            'first_name' => ['required','string'],
            'last_name' => ['required','string'],
            'image_url' => ['nullable','url'],
        ];
    }
}
