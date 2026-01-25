<?php

/**
 * Application Version Configuration
 *
 * This file reads the version from the VERSION file in the project root.
 * The VERSION file is the single source of truth for the application version.
 *
 * Version format follows Semantic Versioning (SemVer):
 * - MAJOR.MINOR.PATCH (e.g., 1.0.0)
 * - Pre-release versions: 1.0.0-alpha, 1.0.0-beta.1
 * - Build metadata: 1.0.0+build.123
 */

$versionFile = base_path('../VERSION');

// Read version from VERSION file, fallback to 'dev' if not found
$version = 'dev';
if (file_exists($versionFile)) {
    $version = trim(file_get_contents($versionFile));
}

return [
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | The current version of the application, read from the VERSION file.
    | This is displayed in the UI and can be used for cache busting,
    | API versioning, and debugging.
    |
    */
    'number' => $version,

    /*
    |--------------------------------------------------------------------------
    | Version Display Name
    |--------------------------------------------------------------------------
    |
    | A formatted version string for display purposes.
    | Prefixed with 'v' for user-facing contexts.
    |
    */
    'display' => 'v' . $version,

    /*
    |--------------------------------------------------------------------------
    | Build Information
    |--------------------------------------------------------------------------
    |
    | Additional build metadata that can be set during CI/CD.
    | These can be overridden via environment variables.
    |
    */
    'build' => [
        'commit' => env('GIT_COMMIT', null),
        'branch' => env('GIT_BRANCH', null),
        'date' => env('BUILD_DATE', null),
    ],
];
