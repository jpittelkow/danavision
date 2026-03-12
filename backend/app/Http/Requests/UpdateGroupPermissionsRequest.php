<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.permission' => ['required', 'string', 'max:100'],
            'permissions.*.resource_type' => ['nullable', 'string', 'max:50'],
            'permissions.*.resource_id' => ['nullable', 'integer'],
        ];
    }
}
