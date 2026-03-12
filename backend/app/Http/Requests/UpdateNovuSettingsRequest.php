<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNovuSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'api_key' => ['sometimes', 'nullable', 'string'],
            'app_identifier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_url' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'socket_url' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
        ];
    }
}
