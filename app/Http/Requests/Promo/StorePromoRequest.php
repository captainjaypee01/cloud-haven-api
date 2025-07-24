<?php

namespace App\Http\Requests\Promo;

use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class StorePromoRequest extends FormRequest
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

        return $this->user()->role === "admin";
    }

    public function rules(): array
    {
        return [
            'code'           => 'required|string|max:50|unique:promos,code',  // promo code must be unique
            'discount_type'  => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => 'required|numeric|min:0',  // positive number (if percentage, this is percentage value)
            'expires_at'     => 'nullable|date',           // valid date/time string
            'max_uses'       => 'nullable|integer|min:1',  // optional max usage count
            // 'uses_count' should not be provided by user (managed by system)
            'active'         => 'sometimes|string',                // optional, default false if not present
        ];
    }
}
