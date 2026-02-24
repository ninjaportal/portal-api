<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\Concerns\BuildsTranslatableRules;
use NinjaPortal\Portal\Models\ApiProduct;

class StoreApiProductRequest extends FormRequest
{
    use BuildsTranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $translatable = (new ApiProduct)->getTranslatableAttributes();
        $translationRules = $this->translatableRules($translatable, null, [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'thumbnail' => ['sometimes', 'nullable'],
        ]);

        return array_merge([
            'slug' => ['required', 'string', 'max:255'],
            'swagger_url' => ['sometimes', 'nullable'],
            'integration_file' => ['sometimes', 'nullable'],
            'apigee_product_id' => ['nullable', 'string'],
            'visibility' => ['required', 'in:public,private,draft'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'custom_attributes' => ['sometimes', 'array'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer'],
            'audience_ids' => ['sometimes', 'array'],
            'audience_ids.*' => ['integer'],
        ], $translationRules);
    }
}
