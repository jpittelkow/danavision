<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SuspiciousActivityService;
use Illuminate\Http\JsonResponse;

class SuspiciousActivityController extends Controller
{
    /**
     * Get current suspicious activity alerts (read-only check for dashboard).
     */
    public function index(SuspiciousActivityService $service): JsonResponse
    {
        $alerts = $service->check();

        return response()->json([
            'alerts' => $alerts,
            'has_alerts' => ! empty($alerts),
        ]);
    }
}
