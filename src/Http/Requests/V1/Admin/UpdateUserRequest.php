<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'string', 'min:6'],
            'status' => ['sometimes', 'string'],
            'custom_attributes' => ['sometimes', 'array'],
            'sync_with_apigee' => ['sometimes', 'boolean'],
            'audience_ids' => ['sometimes', 'array'],
            'audience_ids.*' => ['integer'],
        ];
    }
}
