<?php

namespace App\Services\Backup\Destinations;

use Illuminate\Support\Facades\Storage;

class SFTPDestination implements DestinationInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $path;
    private string $disk;

    public function __construct()
    {
        $this->host = config('backup.destinations.sftp.host', '');
        $this->port = (int) config('backup.destinations.sftp.port', 22);
        $this->username = config('backup.destinations.sftp.username', '');
        $this->path = rtrim(config('backup.destinations.sftp.path', '/backups'), '/');
        $this->disk = 'sftp-backups';

        // Configure the disk at runtime if not already configured
        if (!config("filesystems.disks.{$this->disk}")) {
            $config = [
                'driver' => 'sftp',
                'host' => $this->host,
                'port' => $this->port,
                'username' => $this->username,
                'root' => $this->path,
            ];

            // Use password or private key
            if ($password = config('backup.destinations.sftp.password')) {
                $config['password'] = $password;
            }

            if ($privateKey = config('backup.destinations.sftp.private_key')) {
                $config['privateKey'] = $privateKey;
                if ($passphrase = config('backup.destinations.sftp.passphrase')) {
                    $config['passphrase'] = $passphrase;
                }
            }

            config(["filesystems.disks.{$this->disk}" => $config]);
        }
    }

    public function upload(string $localPath, string $filename): array
    {
        Storage::disk($this->disk)->put($filename, file_get_contents($localPath));

        return [
            'success' => true,
            'filename' => $filename,
            'remote_path' => "{$this->path}/{$filename}",
            'host' => $this->host,
        ];
    }

    public function download(string $filename, string $localPath): array
    {
        if (!Storage::disk($this->disk)->exists($filename)) {
            throw new \RuntimeException("Backup not found on SFTP: {$filename}");
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
        if (empty($this->host) || empty($this->username)) {
            return false;
        }

        try {
            Storage::disk($this->disk)->files();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'SFTP';
    }
}
