<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\Concerns\BuildsTranslatableRules;
use NinjaPortal\Portal\Models\Category;

class UpdateCategoryRequest extends FormRequest
{
    use BuildsTranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $translatable = (new Category)->getTranslatableAttributes();
        $translationRules = $this->translatableRules($translatable, null, [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'thumbnail' => ['sometimes', 'nullable'],
        ]);

        return array_merge([
            'slug' => ['sometimes', 'string', 'max:255'],
        ], $translationRules);
    }
}
