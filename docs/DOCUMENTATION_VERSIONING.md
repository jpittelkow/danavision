# DanaVision Versioning System

This document describes the versioning system used in DanaVision.

## Overview

DanaVision uses [Semantic Versioning 2.0.0](https://semver.org/) (SemVer) for release management. The version follows the format `MAJOR.MINOR.PATCH`:

- **MAJOR**: Incompatible API changes or significant rewrites
- **MINOR**: New functionality in a backwards compatible manner
- **PATCH**: Backwards compatible bug fixes

Pre-release versions may use suffixes like `-alpha`, `-beta.1`, `-rc.1`.

## Single Source of Truth

The `VERSION` file at the project root is the single source of truth for the application version. All other version references are synchronized from this file.

### Files That Track Version

| File | Purpose |
|------|---------|
| `VERSION` | **Primary** - Single source of truth |
| `backend/package.json` | npm package version |
| `backend/composer.json` | Composer package version |
| `backend/config/version.php` | Laravel config (reads VERSION file) |

## Accessing the Version

### Backend (PHP/Laravel)

```php
// Get version number (e.g., "1.0.0")
$version = config('version.number');

// Get display version (e.g., "v1.0.0")
$displayVersion = config('version.display');

// Get build info (if set via CI)
$commit = config('version.build.commit');
$branch = config('version.build.branch');
$date = config('version.build.date');
```

### Frontend (React/TypeScript)

The version is shared via Inertia on every page request:

```tsx
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

function MyComponent() {
  const { app } = usePage<PageProps>().props;
  
  console.log(app.version); // "v1.0.0"
  console.log(app.name);    // "DanaVision"
}
```

The version is displayed in the application sidebar footer.

## Version Bump Script

Use the `scripts/bump-version.sh` script to update versions across all files:

### Basic Usage

```bash
# Show current version and help
./scripts/bump-version.sh --help

# Bump patch version (1.0.0 -> 1.0.1)
./scripts/bump-version.sh patch

# Bump minor version (1.0.0 -> 1.1.0)
./scripts/bump-version.sh minor

# Bump major version (1.0.0 -> 2.0.0)
./scripts/bump-version.sh major
```

### Setting Specific Versions

```bash
# Set a specific version
./scripts/bump-version.sh --set 2.0.0

# Set a pre-release version
./scripts/bump-version.sh --set 2.0.0-beta.1

# Set a version with build metadata
./scripts/bump-version.sh --set 2.0.0+build.123
```

### Creating Git Tags

```bash
# Bump and create git tag
./scripts/bump-version.sh minor --tag

# Bump, tag, and push to remote
./scripts/bump-version.sh patch --tag --push
```

### What the Script Updates

1. `VERSION` file
2. `backend/package.json` version field
3. `backend/composer.json` version field
4. Optionally creates a git tag (e.g., `v1.0.0`)

## Release Workflow

### 1. Prepare Release

1. Ensure all changes are committed
2. Update `CHANGELOG.md` with release notes
3. Run the version bump script:

```bash
./scripts/bump-version.sh minor --tag
```

### 2. Commit Version Bump

```bash
git add VERSION backend/package.json backend/composer.json CHANGELOG.md
git commit -m "Release v1.1.0"
```

### 3. Push Release

```bash
# Push commits
git push origin main

# Push tag (triggers CI/CD)
git push origin v1.1.0
```

### 4. CI/CD Builds Docker Image

The GitHub Actions workflow automatically:
- Runs tests
- Builds Docker image
- Tags image with version (e.g., `ghcr.io/jpittelkow/danavision:1.1.0`)
- Pushes to GitHub Container Registry

## Docker Image Tags

When you push a git tag, the CI creates Docker images with multiple tags:

| Git Tag | Docker Tags Created |
|---------|---------------------|
| `v1.2.3` | `1.2.3`, `1.2`, `1`, `latest` |
| `v2.0.0-beta.1` | `2.0.0-beta.1` |

## Build Metadata (CI/CD)

During CI/CD builds, you can inject build metadata via environment variables:

| Environment Variable | Description |
|---------------------|-------------|
| `GIT_COMMIT` | Git commit SHA |
| `GIT_BRANCH` | Git branch name |
| `BUILD_DATE` | Build timestamp |

These are accessible via `config('version.build.*')` in Laravel.

## Changelog

All notable changes are documented in `CHANGELOG.md` following the [Keep a Changelog](https://keepachangelog.com/) format.

### Changelog Sections

- **Added** - New features
- **Changed** - Changes in existing functionality
- **Deprecated** - Soon-to-be removed features
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Vulnerability fixes

## Best Practices

1. **Always use the bump script** - Don't manually edit version files
2. **Update CHANGELOG before releasing** - Document what changed
3. **Use semantic versioning correctly**:
   - Bug fix only → PATCH
   - New feature (backwards compatible) → MINOR
   - Breaking change → MAJOR
4. **Tag releases** - Use `--tag` flag for releases
5. **Don't skip versions** - Increment sequentially
