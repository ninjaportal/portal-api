<?php

namespace NinjaPortal\Api\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NinjaPortal\Api\Support\PortalApiContext;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $table = $user && method_exists($user, 'getTable')
            ? $user->getTable()
            : app(PortalApiContext::class)->consumerTable();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique($table, 'email')->ignore($user?->getKey()),
            ],
        ];
    }
}
