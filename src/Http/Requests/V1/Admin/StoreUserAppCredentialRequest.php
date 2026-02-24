<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAppCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'api_products' => ['required', 'array'],
            'api_products.*' => ['string'],
            'expires_in' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
