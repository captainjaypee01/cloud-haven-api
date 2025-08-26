<?php

namespace App\Http\Requests\Room;

use App\Enums\RoomStatusEnum;
use App\Http\Responses\ValidationErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class UpdateRoomRequest extends FormRequest
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
        $roomId = $this->room;
        return [
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'short_description'     => 'nullable|string',
            'quantity'              => 'required|integer|min:1',
            'max_guests'            => 'required|integer|min:1',
            'extra_guests'          => 'sometimes',
            'allows_day_use'        => 'required|boolean',
            'base_weekday_rate'     => 'required|numeric|min:0',
            'base_weekend_rate'     => 'required|numeric|min:0',
            'price_per_night'       => 'required|numeric|min:0',
            'status'                => ['required','string', Rule::in(RoomStatusEnum::labels())],
            'image_ids'             => 'sometimes|array',
            'image_ids.*'           => 'integer|exists:images,id',
            'amenity_ids'           => 'nullable|array',
            'amenity_ids.*'         => 'exists:amenities,id',
        ];
    }
    
    /**
     * Custom error message
     */
    public function messages()
    {
        return [
            'status.in' => 'Invalid status. Valid values: unavailable, available, archived',
        ];
    }
}
