<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'label' => ['nullable', 'string'],
            'type' => ['required', 'string'],
            'setting_group_id' => ['nullable', 'integer'],
        ];
    }
}
