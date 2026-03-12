<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardized JSON response helpers.
 * Convention: use "message" for success/error text, "data" for payload, "meta" for pagination when applicable.
 */
trait ApiResponseTrait
{
    protected function successResponse(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data), $status);
    }

    protected function createdResponse(string $message, array $data = []): JsonResponse
    {
        return $this->successResponse($message, $data, 201);
    }

    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * Success response for delete operations (200 with message). Use for consistent delete responses.
     */
    protected function deleteResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return response()->json(['message' => $message], 200);
    }

    protected function dataResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }
}
