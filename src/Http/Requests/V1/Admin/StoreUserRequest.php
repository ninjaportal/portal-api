<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['sometimes', 'string'],
            'custom_attributes' => ['sometimes', 'array'],
            'audience_ids' => ['sometimes', 'array'],
            'audience_ids.*' => ['integer'],
            'sync_with_apigee' => ['sometimes', 'boolean'],
        ];
    }
}
