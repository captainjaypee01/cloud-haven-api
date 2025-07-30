<?php

namespace App\Http\Requests\Amenity;

use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class UpdateAmenityRequest extends FormRequest
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

        return in_array($this->user()->role, ['admin', 'superadmin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', Rule::unique('amenities')->ignore($this->amenity)],
            'description' => 'sometimes',
            'icon' => 'nullable|string',
            'price' => 'nullable|numeric',
            'status' => 'nullable|string',
        ];
    }
}
