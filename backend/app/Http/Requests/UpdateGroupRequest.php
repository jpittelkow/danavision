<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('user_groups', 'slug')->ignore($group->id)],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
