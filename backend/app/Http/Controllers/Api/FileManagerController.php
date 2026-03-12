<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilePathRequest;
use App\Services\AuditService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    public function __construct(
        private StorageService $storageService,
        private AuditService $auditService
    ) {}

    /**
     * List files and directories (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $path = $request->input('path', '');
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && ! $this->validatePath($path)) {
            return response()->json(['message' => 'Invalid path.'], 422);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', config('app.pagination.default'))));

        try {
            $result = $this->storageService->listFiles($path, $page, $perPage);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('File listing failed', ['path' => $path, 'exception' => $e]);
            return response()->json(['message' => 'Failed to list files.'], 500);
        }
    }

    /**
     * Get file or directory details.
     */
    public function show(FilePathRequest $request): JsonResponse
    {
        $path = $request->getPath();
        $info = $this->storageService->getFileInfo($path);
        if ($info === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $info['previewUrl'] = $this->storageService->getPreviewUrl($path);
        return response()->json($info);
    }

    /**
     * Upload file(s).
     */
    public function upload(Request $request): JsonResponse
    {
        $path = $request->input('path', '');
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && ! $this->validatePath($path)) {
            return response()->json(['message' => 'Invalid path.'], 422);
        }

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file'],
        ]);

        $files = $request->file('files');
        $files = is_array($files) ? $files : ($files ? [$files] : []);

        $policy = $this->storageService->getUploadPolicy();

        $uploaded = [];
        $errors = [];
        foreach ($files as $file) {
            $error = $this->storageService->validateUpload($file, $policy);
            if ($error !== null) {
                $errors[] = $error;
                continue;
            }

            try {
                $result = $this->storageService->uploadFile($file, $path);
                $uploaded[] = $result;
                $this->auditService->log('file.uploaded', null, [], [
                    'path' => $result['path'],
                    'filename' => $result['name'],
                    'size' => $result['size'],
                ]);
            } catch (\Throwable $e) {
                Log::error('File upload failed', ['filename' => $file->getClientOriginalName(), 'exception' => $e]);
                $errors[] = $file->getClientOriginalName() . ': upload failed.';
            }
        }

        if (empty($uploaded)) {
            return response()->json([
                'message' => count($errors) > 0 ? 'Upload failed.' : 'No files to upload.',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'message' => 'Files uploaded.',
            'uploaded' => $uploaded,
            'errors' => $errors,
        ], 201);
    }

    /**
     * Download a file.
     */
    public function download(FilePathRequest $request): StreamedResponse|JsonResponse
    {
        $path = $request->getPath();
        try {
            $response = $this->storageService->downloadFile($path);
            $this->auditService->log('file.downloaded', null, [], ['path' => $path]);
            return $response;
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            return response()->json(['message' => 'Not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('File download failed', ['path' => $path, 'exception' => $e]);
            return response()->json(['message' => 'Download failed.'], 500);
        }
    }

    /**
     * Delete a file or directory.
     */
    public function destroy(FilePathRequest $request): JsonResponse
    {
        $path = $request->getPath();
        try {
            $this->storageService->deleteFile($path);
            $this->auditService->log('file.deleted', null, [], ['path' => $path]);
            return response()->json(['message' => 'Deleted.']);
        } catch (\Throwable $e) {
            Log::error('File deletion failed', ['path' => $path, 'exception' => $e]);
            return response()->json(['message' => 'Delete failed.'], 500);
        }
    }

    /**
     * Rename a file or directory.
     */
    public function rename(FilePathRequest $request): JsonResponse
    {
        $path = $request->getPath();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
        ]);
        $newName = $validated['name'];
        if (str_contains($newName, '..')) {
            return response()->json(['message' => 'Invalid name.'], 422);
        }
        try {
            $this->storageService->renameFile($path, $newName);
            $parent = dirname($path);
            $newPath = ($parent === '.' || $parent === '') ? $newName : $parent . '/' . $newName;
            $this->auditService->log('file.renamed', null, ['path' => $path], ['path' => $newPath]);
            return response()->json(['message' => 'Renamed.', 'path' => $newPath]);
        } catch (\Throwable $e) {
            Log::error('File rename failed', ['path' => $path, 'newName' => $newName, 'exception' => $e]);
            return response()->json(['message' => 'Rename failed.'], 500);
        }
    }

    /**
     * Move a file or directory.
     */
    public function move(FilePathRequest $request): JsonResponse
    {
        $path = $request->getPath();
        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:2048'],
        ]);
        $destination = trim(str_replace('\\', '/', $validated['destination']), '/');
        if (! $this->validatePath($destination)) {
            return response()->json(['message' => 'Invalid destination path.'], 422);
        }
        try {
            $this->storageService->moveFile($path, $destination);
            $name = basename($path);
            $newPath = ($destination === '' || $destination === '.') ? $name : $destination . '/' . $name;
            $this->auditService->log('file.moved', null, ['path' => $path], ['path' => $newPath]);
            return response()->json(['message' => 'Moved.', 'path' => $newPath]);
        } catch (\Throwable $e) {
            Log::error('File move failed', ['path' => $path, 'destination' => $destination, 'exception' => $e]);
            return response()->json(['message' => 'Move failed.'], 500);
        }
    }

    private function validatePath(string $path): bool
    {
        if (str_contains($path, '..') || preg_match('#\0#', $path)) {
            return false;
        }
        $blocked = ['.env', 'config', '.git', 'bootstrap', 'vendor'];
        $segments = $path === '' ? [] : explode('/', trim($path, '/'));
        foreach ($segments as $segment) {
            if (in_array(strtolower($segment), $blocked, true)) {
                return false;
            }
        }
        return true;
    }
}
