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
            'starts_at'      => 'nullable|date_format:Y-m-d\TH:i',
            'ends_at'        => [
                'nullable',
                'date_format:Y-m-d\TH:i',
                function ($attribute, $value, $fail) {
                    $startsAt = $this->input('starts_at');
                    if ($startsAt && $value && strtotime($value) < strtotime($startsAt)) {
                        $fail('The end date must be after or equal to the start date.');
                    }
                },
            ],
            'expires_at'     => 'nullable|date',
            'max_uses'       => 'nullable|integer|min:1',
            'image_url'      => 'nullable|url',
            'exclusive'      => 'sometimes|boolean',
            'active'         => 'sometimes|string',
            'excluded_days'  => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        foreach ($value as $day) {
                            if (!is_numeric($day) || $day < 0 || $day > 6) {
                                $fail('Each excluded day must be a number between 0 (Sunday) and 6 (Saturday).');
                                return;
                            }
                        }
                    }
                },
            ],
            'excluded_days.*' => 'integer|min:0|max:6',
            'per_night_calculation' => 'sometimes|boolean',
            // We do not expect 'active' or 'uses_count' in a standard update form (manage separately).
        ];
    }
}
