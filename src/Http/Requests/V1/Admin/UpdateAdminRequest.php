<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'string', 'min:6'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }
}
