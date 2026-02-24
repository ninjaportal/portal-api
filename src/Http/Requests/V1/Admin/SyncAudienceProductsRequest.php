<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncAudienceProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'api_product_ids' => ['required', 'array'],
            'api_product_ids.*' => ['integer'],
        ];
    }
}
