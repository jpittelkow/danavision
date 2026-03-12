<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VersionService;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __construct(
        private VersionService $versionService
    ) {}

    /**
     * Get version information.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->versionService->getVersionInfo());
    }
}
