<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'string', 'max:255'],
            'callbackUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:approved,revoked'],
            'apiProducts' => ['sometimes', 'array'],
            'apiProducts.*' => ['string'],
        ];
    }
}
