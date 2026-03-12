# Error Handling Patterns

Backend and frontend error handling for consistent user feedback.

## Backend Error Response Format

```php
// Consistent error response structure
return response()->json([
    'message' => 'Human-readable error message.',
    'errors' => ['field_name' => ['Specific field error.']],
], 422);

// Common error responses
return response()->json(['message' => 'Unauthenticated.'], 401);
return response()->json(['message' => 'Forbidden.'], 403);
return response()->json(['message' => 'Not found.'], 404);
return response()->json(['message' => 'Server error.'], 500);

// Using Laravel's abort helper
abort(404, 'Example not found.');
abort(403, 'You do not have permission to access this resource.');
```

## Backend Exception Handling in Services

```php
class ExampleService
{
    public function process(Example $example, array $options = []): ProcessResult
    {
        try {
            $result = $this->doProcessing($example, $options);
            return new ProcessResult(success: true, data: $result);
        } catch (ValidationException $e) {
            throw $e; // Let Laravel handle
        } catch (ExternalApiException $e) {
            Log::warning('External API failed', ['example_id' => $example->id, 'error' => $e->getMessage()]);
            return new ProcessResult(success: false, error: 'External service temporarily unavailable.');
        } catch (\Exception $e) {
            Log::error('Unexpected error', ['example_id' => $example->id, 'error' => $e->getMessage()]);
            return new ProcessResult(success: false, error: 'An unexpected error occurred.');
        }
    }
}
```

## Frontend Error Handling

```tsx
const handleSubmit = async (data: FormData) => {
  try {
    setSubmitting(true);
    setError(null);
    await api.post('/examples', data);
    toast.success('Example created successfully');
  } catch (err: any) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      Object.entries(err.response.data.errors).forEach(([field, messages]) => {
        form.setError(field as any, { message: (messages as string[])[0] });
      });
    } else if (err.response?.status === 401) {
      toast.error('Session expired. Please log in again.');
    } else if (err.response?.status === 403) {
      toast.error('You do not have permission to perform this action');
    } else {
      const message = err.response?.data?.message || 'Something went wrong';
      toast.error(message);
    }
  } finally {
    setSubmitting(false);
  }
};
```

## Frontend Error Boundary

```tsx
export class ErrorBoundary extends Component<Props, State> {
  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback || (
        <Card className="max-w-md mx-auto mt-8">
          <CardHeader><CardTitle>Something went wrong</CardTitle></CardHeader>
          <CardContent>
            <Button onClick={() => this.setState({ hasError: false })}>Try Again</Button>
          </CardContent>
        </Card>
      );
    }
    return this.props.children;
  }
}
```

## API Response Status Codes

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | OK | Successful GET, PUT; DELETE (with message body via `deleteResponse()`) |
| 201 | Created | Successful POST that creates a resource |
| 400 | Bad Request | Malformed request |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | Authenticated but not allowed |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected backend error |

**Key files:** `frontend/components/error-boundary.tsx`, `frontend/lib/api.ts`, `backend/app/Exceptions/Handler.php`

**Related:** [API Calls](api-calls.md), [Controller](controller.md), [Logging](logging.md)
