<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilePathRequest extends FormRequest
{
    /**
     * Paths or segments that are not allowed (sensitive directories).
     */
    private const BLOCKED_SEGMENTS = [
        '.env',
        'config',
        '.git',
        'bootstrap',
        'vendor',
        '.phpunit.result.cache',
        'phpunit.xml',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $path = is_string($value) ? $value : '';

                    if (str_contains($path, '..')) {
                        $fail('Invalid path: directory traversal is not allowed.');
                        return;
                    }

                    if (preg_match('#\0#', $path)) {
                        $fail('Invalid path: null bytes are not allowed.');
                        return;
                    }

                    $normalized = trim(str_replace('\\', '/', $path), '/');
                    $segments = $normalized === '' ? [] : explode('/', $normalized);

                    foreach ($segments as $segment) {
                        if ($segment === '' || $segment === '.') {
                            continue;
                        }
                        $lower = strtolower($segment);
                        foreach (self::BLOCKED_SEGMENTS as $blocked) {
                            if ($lower === strtolower($blocked)) {
                                $fail('Access to this path is not allowed.');
                                return;
                            }
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get the path value (from route or request).
     */
    public function getPath(): string
    {
        $path = $this->route('path') ?? $this->input('path', '');
        return trim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Prepare the path for validation when it comes from the route.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('path') !== null && ! $this->has('path')) {
            $this->merge(['path' => $this->route('path')]);
        }
    }
}
