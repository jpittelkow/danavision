# Form Request Pattern

Use dedicated FormRequest classes for input validation. Never use inline `$request->validate()` in controllers.

## Usage

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by route middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:typeA,typeB,typeC'],
            'settings' => ['nullable', 'array'],
            'settings.key' => ['required_with:settings', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name.',
            'type.in' => 'Invalid type selected.',
        ];
    }
}
```

**Key files:** `backend/app/Http/Requests/`

**Related:** [Controller](controller.md), [Anti-patterns: Backend](../anti-patterns/backend.md#dont-skip-form-request-validation)
