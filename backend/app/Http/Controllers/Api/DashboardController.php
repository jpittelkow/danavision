<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;
    /**
     * Get dashboard stats for the stats widget.
     */
    public function stats(Request $request): JsonResponse
    {
        $storagePath = storage_path();
        $diskTotal = disk_total_space($storagePath);
        $diskFree = disk_free_space($storagePath);
        $diskTotal = $diskTotal !== false ? (int) $diskTotal : 0;
        $diskFree = $diskFree !== false ? (int) $diskFree : 0;
        $storageUsed = $diskTotal - $diskFree;

        $metrics = [
            ['label' => 'Total Users', 'value' => User::count()],
            ['label' => 'Storage Used', 'value' => FileHelper::formatBytes($storageUsed)],
        ];

        return response()->json(['metrics' => $metrics]);
    }

    /**
     * Get environment info for the environment widget.
     */
    public function environment(): JsonResponse
    {
        return response()->json([
            'environment' => config('app.env', 'production'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => config('database.default'),
        ]);
    }
}
