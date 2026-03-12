<?php

namespace App\Services\Backup\Destinations;

interface DestinationInterface
{
    /**
     * Upload a backup file to the destination.
     *
     * @param string $localPath The local path to the backup file
     * @param string $filename The destination filename
     * @return array Result with 'success' and additional metadata
     */
    public function upload(string $localPath, string $filename): array;

    /**
     * Download a backup file from the destination.
     *
     * @param string $filename The filename to download
     * @param string $localPath The local path to save to
     * @return array Result with 'success' and local path
     */
    public function download(string $filename, string $localPath): array;

    /**
     * List available backups at the destination.
     *
     * @return array List of backup files with metadata
     */
    public function list(): array;

    /**
     * Delete a backup from the destination.
     *
     * @param string $filename The filename to delete
     * @return bool Success status
     */
    public function delete(string $filename): bool;

    /**
     * Check if the destination is configured and accessible.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the destination name.
     *
     * @return string
     */
    public function getName(): string;
}
