<?php

namespace App\Http\Middleware;

use App\Services\AccessLogService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogResourceAccess
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', '_token', 'api_token'];

    public function __construct(
        private AccessLogService $accessLog
    ) {}

    /**
     * Handle an incoming request. Log access to PHI for HIPAA compliance.
     *
     * @param  string  $resourceType  User, Setting, etc. (from middleware parameter)
     */
    public function handle(Request $request, Closure $next, string $resourceType = 'User'): Response
    {
        $response = $next($request);

        if (! $request->user()) {
            return $response;
        }

        $action = $this->actionFromMethod($request->method());
        $resourceId = $this->resolveResourceId($request, $resourceType);
        $fieldsAccessed = $this->extractFields($request, $response, $action);

        $this->accessLog->log($action, $resourceType, $resourceId, $fieldsAccessed, $request);

        return $response;
    }

    private function extractFields(Request $request, Response $response, string $action): ?array
    {
        if (in_array($action, ['create', 'update'])) {
            $input = $request->all();
            if (empty($input)) {
                return null;
            }
            $keys = $this->flattenKeys($input);
            $filtered = $this->filterSensitiveKeys($keys);

            return $filtered ?: null;
        }

        if ($action === 'view' && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (! is_array($data) || empty($data)) {
                return null;
            }
            if (isset($data['data']) && is_array($data['data'])) {
                $inner = $data['data'];
                $first = $inner[0] ?? $inner;
                if (is_array($first) && ! array_is_list($first)) {
                    $data = $first;
                } elseif (is_array($inner) && ! array_is_list($inner)) {
                    $data = $inner;
                } else {
                    return null;
                }
            }
            if (empty($data) || ! is_array($data)) {
                return null;
            }
            $keys = $this->flattenKeys($data);
            $filtered = $this->filterSensitiveKeys($keys);

            return $filtered ?: null;
        }

        return null;
    }

    private function flattenKeys(array $data, string $prefix = ''): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value) && ! array_is_list($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }

    private function filterSensitiveKeys(array $keys): array
    {
        return array_values(array_filter($keys, function (string $key) {
            $lower = strtolower($key);
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lower, $sensitive)) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function actionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    private function resolveResourceId(Request $request, string $resourceType): ?int
    {
        $param = strtolower($resourceType);

        $model = $request->route($param);
        if ($model && is_object($model) && method_exists($model, 'getKey')) {
            return (int) $model->getKey();
        }
        if (is_numeric($model)) {
            return (int) $model;
        }

        if ($resourceType === 'User' && str_contains($request->path(), 'profile')) {
            return $request->user()?->id;
        }

        if ($resourceType === 'Setting' && str_contains($request->path(), 'user/')) {
            return $request->user()?->id;
        }

        return null;
    }
}
