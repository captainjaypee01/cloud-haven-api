<?php

namespace App\Http\Requests\Promo;

use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class UpdatePromoRequest extends FormRequest
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

    public function rules(): array
    {
        $promoId = $this->promo;
        return [
            'code'           => [
                'required',
                'string',
                'max:50',
                Rule::unique('promos', 'code')->ignore($promoId),
            ],
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'scope'          => 'nullable|string|max:100',
            'discount_type'  => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => 'required|numeric|min:0',
            'expires_at'     => 'nullable|date',
            'max_uses'       => 'nullable|integer|min:1',
            'image_url'      => 'nullable|url',
            'exclusive'      => 'sometimes|boolean',
            'active'         => 'sometimes|string',
            // We do not expect 'active' or 'uses_count' in a standard update form (manage separately).
        ];
    }
}
