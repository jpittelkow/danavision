<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
