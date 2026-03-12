# Controller Pattern

Controllers use `ApiResponseTrait` for consistent JSON responses and `config('app.pagination.default')` for pagination.

## Usage

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExampleRequest;
use App\Http\Requests\UpdateExampleRequest;
use App\Http\Resources\ExampleResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Example;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExampleController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $examples = Example::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(config('app.pagination.default'));

        return $this->dataResponse([
            'data' => ExampleResource::collection($examples),
            'meta' => [
                'current_page' => $examples->currentPage(),
                'last_page' => $examples->lastPage(),
                'total' => $examples->total(),
            ],
        ]);
    }

    public function store(StoreExampleRequest $request): JsonResponse
    {
        $example = Example::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->createdResponse('Example created successfully.', [
            'data' => new ExampleResource($example),
        ]);
    }

    public function show(Request $request, Example $example): JsonResponse
    {
        $this->authorize('view', $example);
        return $this->dataResponse(['data' => new ExampleResource($example)]);
    }

    public function update(UpdateExampleRequest $request, Example $example): JsonResponse
    {
        $this->authorize('update', $example);
        $example->update($request->validated());
        return $this->successResponse('Example updated successfully.', [
            'data' => new ExampleResource($example),
        ]);
    }

    public function destroy(Request $request, Example $example): JsonResponse
    {
        $this->authorize('delete', $example);
        $example->delete();
        return $this->successResponse('Example deleted successfully.');
    }
}
```

## ApiResponseTrait

Standardized JSON response helpers. Convention: use `message` for success/error text, `data` for payload, `meta` for pagination.

| Method | Use |
|--------|-----|
| `successResponse($message, $data = [], $status = 200)` | Success with message and optional data |
| `createdResponse($message, $data = [])` | 201 created |
| `errorResponse($message, $status = 400)` | Error with message |
| `deleteResponse($message = 'Resource deleted successfully')` | 200 with message (use for delete operations) |
| `dataResponse($data, $status = 200)` | Raw data (e.g. paginated list) |

Delete endpoints use **200 with a message body** (not 204) for consistency. Use `deleteResponse()` for delete operations.

## Pagination Config

Use `config('app.pagination.default')` (default 20) for list endpoints. Audit logs use `config('app.pagination.audit_log')` (50).

```php
$perPage = $request->input('per_page', config('app.pagination.default'));
$logs = AuditLog::paginate($request->input('per_page', config('app.pagination.audit_log')));
```

**Key files:** `backend/app/Http/Controllers/Api/`, `backend/app/Http/Traits/ApiResponseTrait.php`

**Related:** [Form Request](form-request.md), [Resource](resource.md), [Anti-patterns: Backend](../anti-patterns/backend.md)
