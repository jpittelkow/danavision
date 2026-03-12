<?php

namespace App\Services\Backup\Destinations;

use Illuminate\Support\Facades\Storage;

class S3Destination implements DestinationInterface
{
    private string $bucket;
    private string $prefix;
    private string $disk;

    public function __construct()
    {
        $this->bucket = config('backup.destinations.s3.bucket', '');
        // Use 'path' config key (as defined in backup.php config)
        $this->prefix = rtrim(config('backup.destinations.s3.path', 'backups'), '/');
        $this->disk = 's3-backups';

        // Configure the disk at runtime if not already configured
        if (!config("filesystems.disks.{$this->disk}")) {
            config(["filesystems.disks.{$this->disk}" => [
                'driver' => 's3',
                'key' => config('backup.destinations.s3.key', env('AWS_ACCESS_KEY_ID')),
                'secret' => config('backup.destinations.s3.secret', env('AWS_SECRET_ACCESS_KEY')),
                'region' => config('backup.destinations.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
                'bucket' => $this->bucket,
                'url' => config('backup.destinations.s3.url'),
                'endpoint' => config('backup.destinations.s3.endpoint'),
                'use_path_style_endpoint' => config('backup.destinations.s3.use_path_style_endpoint', false),
            ]]);
        }
    }

    public function upload(string $localPath, string $filename): array
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;

        Storage::disk($this->disk)->put($remotePath, file_get_contents($localPath));

        return [
            'success' => true,
            'filename' => $filename,
            'remote_path' => $remotePath,
            'bucket' => $this->bucket,
            'url' => Storage::disk($this->disk)->url($remotePath),
        ];
    }

    public function download(string $filename, string $localPath): array
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;

        if (!Storage::disk($this->disk)->exists($remotePath)) {
            throw new \RuntimeException("Backup not found in S3: {$filename}");
        }

        $content = Storage::disk($this->disk)->get($remotePath);
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
        $files = Storage::disk($this->disk)->files($this->prefix);

        return collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(fn ($file) => [
                'filename' => basename($file),
                'remote_path' => $file,
                'size' => Storage::disk($this->disk)->size($file),
                'last_modified' => Storage::disk($this->disk)->lastModified($file),
            ])
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    public function delete(string $filename): bool
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;
        return Storage::disk($this->disk)->delete($remotePath);
    }

    public function isAvailable(): bool
    {
        if (empty($this->bucket)) {
            return false;
        }

        try {
            // Try to list files to verify connectivity
            Storage::disk($this->disk)->files($this->prefix);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Amazon S3';
    }
}
