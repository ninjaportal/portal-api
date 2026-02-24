<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\Concerns\BuildsTranslatableRules;
use NinjaPortal\Portal\Models\MenuItem;

class UpdateMenuItemRequest extends FormRequest
{
    use BuildsTranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $translatable = (new MenuItem)->getTranslatableAttributes();
        $translationRules = $this->translatableRules($translatable, null, [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'route' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return array_merge([
            'slug' => ['sometimes', 'string', 'max:255'],
        ], $translationRules);
    }
}
