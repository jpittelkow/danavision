<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ChangelogExportService;
use App\Services\ChangelogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChangelogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ChangelogService $changelogService,
        private ChangelogExportService $exportService,
    ) {}

    /**
     * Get paginated changelog entries.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        // Clamp per_page to reasonable bounds
        $perPage = max(1, min($perPage, 50));

        $result = $this->changelogService->getEntries($page, $perPage);

        return $this->dataResponse($result);
    }

    /**
     * Get available version strings for the export picker.
     */
    public function versions(): JsonResponse
    {
        $versions = $this->exportService->getAvailableVersions();

        return $this->dataResponse(['versions' => $versions]);
    }

    /**
     * Export an AI-readable upgrade guide between two versions.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'from' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'to' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
        ]);

        $from = $request->input('from');
        $to = $request->input('to');

        $available = $this->exportService->getAvailableVersions();

        if (!in_array($from, $available) || !in_array($to, $available)) {
            return $this->errorResponse('One or both versions not found in the changelog.', 422);
        }

        if (version_compare($from, $to, '>=')) {
            return $this->errorResponse('The "from" version must be older than the "to" version.', 422);
        }

        $markdown = $this->exportService->generateExport($from, $to);
        $appName = strtolower(str_replace(' ', '-', config('app.name', 'sourdough')));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', "{$appName}-upgrade-guide-{$from}-to-{$to}.md");

        return response()->stream(
            function () use ($markdown) {
                echo $markdown;
            },
            200,
            [
                'Content-Type' => 'text/markdown',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }
}
