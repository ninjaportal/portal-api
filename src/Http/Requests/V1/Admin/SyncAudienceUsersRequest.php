<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use NinjaPortal\Api\Support\PortalApiContext;

class SyncAudienceUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = app(PortalApiContext::class)->consumerTable();

        return [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', Rule::exists($table, 'id')],
        ];
    }
}
