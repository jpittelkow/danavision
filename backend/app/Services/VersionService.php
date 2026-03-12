<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VersionService
{
    /**
     * Get version information.
     */
    public function getVersionInfo(): array
    {
        return [
            'version' => $this->getVersion(),
            'build_sha' => config('version.build_sha', 'development'),
            'build_time' => config('version.build_time'),
            'schema_version' => config('version.schema_version'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get current version string.
     */
    public function getVersion(): string
    {
        // First check environment variable (set in Docker)
        $version = config('version.version');

        if ($version && $version !== '0.0.0') {
            return $version;
        }

        // Try reading from VERSION file
        $versionFile = base_path('../VERSION');
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        return '0.0.0';
    }

    /**
     * Check for newer version (if enabled).
     */
    public function checkForUpdate(): ?array
    {
        if (!config('version.check_enabled')) {
            return null;
        }

        $checkUrl = config('version.check_url');

        try {
            $response = Http::timeout(10)->get($checkUrl);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $latestVersion = $data['tag_name'] ?? null;

            if (!$latestVersion) {
                return null;
            }

            // Remove 'v' prefix if present
            $latestVersion = ltrim($latestVersion, 'v');
            $currentVersion = $this->getVersion();

            $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

            return [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'has_update' => $hasUpdate,
                'release_url' => $data['html_url'] ?? null,
                'release_notes' => $data['body'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse semantic version string.
     */
    public function parseVersion(string $version): array
    {
        $parts = explode('.', $version);

        return [
            'major' => (int) ($parts[0] ?? 0),
            'minor' => (int) ($parts[1] ?? 0),
            'patch' => (int) ($parts[2] ?? 0),
        ];
    }
}
