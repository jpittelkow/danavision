# Recipe: Extend the File Manager

Add new file operations or modify the file manager behavior.

## Key Files

| File | Purpose |
|------|---------|
| `backend/app/Http/Controllers/Api/FileManagerController.php` | API endpoints for file operations |
| `backend/app/Services/StorageService.php` | Storage abstraction (all file I/O goes through here) |
| `backend/app/Http/Requests/FilePathRequest.php` | Path validation form request |

## Architecture

The file manager is a thin API layer over `StorageService`. All file I/O (list, upload, download, rename, move, delete) delegates to `StorageService`, which abstracts the underlying storage provider.

## Adding a New File Operation

### 1. Add Method to StorageService

```php
// backend/app/Services/StorageService.php
public function copyFile(string $source, string $destination): void
{
    Storage::disk($this->disk)->copy($source, $destination);
}
```

### 2. Add Controller Method

```php
// backend/app/Http/Controllers/Api/FileManagerController.php
public function copy(FilePathRequest $request): JsonResponse
{
    $path = $request->getPath();
    $validated = $request->validate([
        'destination' => ['required', 'string', 'max:2048'],
    ]);
    $destination = trim(str_replace('\\', '/', $validated['destination']), '/');
    if (!$this->validatePath($destination)) {
        return response()->json(['message' => 'Invalid destination path.'], 422);
    }
    try {
        $this->storageService->copyFile($path, $destination);
        $this->auditService->log('file.copied', null, ['source' => $path], ['destination' => $destination]);
        return response()->json(['message' => 'Copied.']);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Copy failed.'], 500);
    }
}
```

### 3. Add Route

```php
Route::post('/files/copy', [FileManagerController::class, 'copy']);
```

## Security Rules

- **Always validate paths** via `validatePath()` — blocks `..`, null bytes, and sensitive directories
- **Always audit** file operations via `AuditService`
- **Use `FilePathRequest`** for endpoints that accept a file path
- **Blocked directories**: `.env`, `config`, `.git`, `bootstrap`, `vendor`

## Checklist

- [ ] Operation delegates to `StorageService` (not direct filesystem access)
- [ ] Path validated (no traversal, no sensitive dirs)
- [ ] Audit log entry created
- [ ] Upload validates file against upload policy

**Related:** [ADR-030](../../adr/030-file-manager.md), [ADR-022](../../adr/022-storage-provider-system.md), [Pattern: Storage Settings](../patterns/storage-settings.md)
