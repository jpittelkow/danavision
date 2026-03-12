<?php

namespace App\Services\Backup\Destinations;

use Illuminate\Support\Facades\Storage;

class LocalDestination implements DestinationInterface
{
    private string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('backup.disk', 'backups');
    }

    public function upload(string $localPath, string $filename): array
    {
        $content = file_get_contents($localPath);
        Storage::disk($this->disk)->put($filename, $content);

        return [
            'success' => true,
            'filename' => $filename,
            'size' => strlen($content),
            'path' => Storage::disk($this->disk)->path($filename),
        ];
    }

    public function download(string $filename, string $localPath): array
    {
        if (!Storage::disk($this->disk)->exists($filename)) {
            throw new \RuntimeException("Backup not found: {$filename}");
        }

        $content = Storage::disk($this->disk)->get($filename);
        file_put_contents($localPath, $content);

        return [
            'success' => true,
            'filename' => $filename,
            'local_path' => $localPath,
            'size' => strlen($content),
        ];
    }

    public function list(): array
    {
        $files = Storage::disk($this->disk)->files();

        return collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(fn ($file) => [
                'filename' => $file,
                'size' => Storage::disk($this->disk)->size($file),
                'last_modified' => Storage::disk($this->disk)->lastModified($file),
            ])
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    public function delete(string $filename): bool
    {
        return Storage::disk($this->disk)->delete($filename);
    }

    public function isAvailable(): bool
    {
        try {
            // Try to write a test file
            $testFile = '.destination-test-' . uniqid();
            Storage::disk($this->disk)->put($testFile, 'test');
            Storage::disk($this->disk)->delete($testFile);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Local Filesystem';
    }
}
