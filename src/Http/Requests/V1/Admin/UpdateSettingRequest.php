<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'nullable', 'string'],
            'label' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string'],
            'setting_group_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
