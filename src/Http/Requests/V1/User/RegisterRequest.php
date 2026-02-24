<?php

namespace NinjaPortal\Api\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NinjaPortal\Api\Support\PortalApiContext;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = app(PortalApiContext::class)->consumerTable();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique($table, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
