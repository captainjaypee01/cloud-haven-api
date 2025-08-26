<?php

namespace App\Http\Requests\Image;

use Illuminate\Foundation\Http\FormRequest;

class StoreImagesRequest extends FormRequest
{
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
            'files'   => 'required|array|max:20',
            'files.*' => 'required|image|max:5120', // max 5MB each
            'names'   => 'required|array',
            'names.*' => 'required|string|max:255',
        ];
    }
}
