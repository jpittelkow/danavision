<?php

namespace App\Services\Backup\Destinations;

use Illuminate\Support\Facades\Http;

class GoogleDriveDestination implements DestinationInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private ?string $folderId;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->clientId = config('backup.destinations.google_drive.client_id', '');
        $this->clientSecret = config('backup.destinations.google_drive.client_secret', '');
        $this->refreshToken = config('backup.destinations.google_drive.refresh_token', '');
        $this->folderId = config('backup.destinations.google_drive.folder_id');
    }

    /**
     * Get an access token using the refresh token.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get Google Drive access token: ' . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    public function upload(string $localPath, string $filename): array
    {
        $accessToken = $this->getAccessToken();
        $content = file_get_contents($localPath);

        // Create file metadata
        $metadata = [
            'name' => $filename,
            'mimeType' => 'application/zip',
        ];

        if ($this->folderId) {
            $metadata['parents'] = [$this->folderId];
        }

        // Use multipart upload
        $boundary = 'backup_' . uniqid();
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/zip\r\n\r\n";
        $body .= $content . "\r\n";
        $body .= "--{$boundary}--";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'multipart/related; boundary=' . $boundary,
        ])->withBody($body, 'multipart/related; boundary=' . $boundary)
          ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to upload to Google Drive: ' . $response->body());
        }

        return [
            'success' => true,
            'filename' => $filename,
            'file_id' => $response->json('id'),
            'web_link' => $response->json('webViewLink'),
        ];
    }

    public function download(string $filename, string $localPath): array
    {
        $accessToken = $this->getAccessToken();
        
        // Find the file by name
        $fileId = $this->findFileId($filename);
        
        if (!$fileId) {
            throw new \RuntimeException("Backup not found on Google Drive: {$filename}");
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get("https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media");

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download from Google Drive: ' . $response->body());
        }

        file_put_contents($localPath, $response->body());

        return [
            'success' => true,
            'filename' => $filename,
            'local_path' => $localPath,
            'size' => strlen($response->body()),
        ];
    }

    public function list(): array
    {
        $accessToken = $this->getAccessToken();
        
        $query = "mimeType='application/zip' and trashed=false";
        if ($this->folderId) {
            $query .= " and '{$this->folderId}' in parents";
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $query,
            'fields' => 'files(id,name,size,modifiedTime)',
            'orderBy' => 'modifiedTime desc',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list Google Drive files: ' . $response->body());
        }

        return collect($response->json('files', []))
            ->map(fn ($file) => [
                'filename' => $file['name'],
                'file_id' => $file['id'],
                'size' => (int) ($file['size'] ?? 0),
                'last_modified' => strtotime($file['modifiedTime'] ?? 'now'),
            ])
            ->all();
    }

    public function delete(string $filename): bool
    {
        $accessToken = $this->getAccessToken();
        $fileId = $this->findFileId($filename);

        if (!$fileId) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");

        return $response->successful();
    }

    /**
     * Find a file's ID by name.
     */
    private function findFileId(string $filename): ?string
    {
        $accessToken = $this->getAccessToken();
        
        $query = "name='{$filename}' and mimeType='application/zip' and trashed=false";
        if ($this->folderId) {
            $query .= " and '{$this->folderId}' in parents";
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $query,
            'fields' => 'files(id)',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $files = $response->json('files', []);
        return $files[0]['id'] ?? null;
    }

    public function isAvailable(): bool
    {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->refreshToken)) {
            return false;
        }

        try {
            $this->getAccessToken();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Google Drive';
    }
}
