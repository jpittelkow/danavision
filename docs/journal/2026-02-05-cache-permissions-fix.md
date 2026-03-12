# Cache Permissions Fix - 2026-02-05

## Overview

Fixed a production bug where clicking "Fetch Models" on the LLM provider configuration page (e.g. adding Claude) would fail with:

```
file_put_contents(/var/www/html/backend/storage/framework/cache/data/68/b8/...): Failed to open stream: No such file or directory
```

The API key test succeeded, but the model discovery step (which caches results via Laravel's file cache) could not write to the cache directory.

## Root Cause

Two issues in the Docker build/startup:

1. **Dockerfile** created `storage/framework/cache` but not the `data` subdirectory that Laravel's file cache driver requires. The `data/` directory was only created at runtime by the entrypoint.

2. **Entrypoint** ran `chown -R www-data:www-data storage` and `chmod -R 775 storage` **before** the artisan commands (`migrate`, `config:cache`, `route:cache`, `view:cache`). Those artisan commands run as root and can create root-owned files/directories inside `storage/`. There was no second ownership pass afterward, so PHP-FPM (running as www-data) could not create cache subdirectories.

This particularly affected NAS deployments (Unraid, Synology) using PUID/PGID, where www-data is remapped to a non-default UID.

## Implementation Approach

- **`docker/Dockerfile`**: Changed `mkdir -p .../storage/framework/cache` to `mkdir -p .../storage/framework/cache/data` so the full path exists in the built image.
- **`docker/entrypoint.sh`**: Added a second `chown`/`chmod` pass on `storage/` and `bootstrap/cache/` after all artisan commands complete, right before the "Sourdough Ready" message.

## Challenges Encountered

The bug only manifested in production on Unraid — not in local development — because:
- Local dev bind-mounts `./backend` from the host (Windows), where permissions are handled differently
- Production bakes the backend into the image and only volume-mounts `storage/app`
- The PUID/PGID remapping added another layer of complexity

## Observations

- Artisan commands running as root in the entrypoint is a recurring source of permission issues (see also the Meilisearch permission fix from 2026-01-30)
- The second `chown` pass is a defensive measure that covers any future artisan commands that might be added to the entrypoint

## Trade-offs

- The second `chown -R` adds a small amount of startup time but is negligible compared to the artisan commands themselves
- An alternative would be running artisan commands as www-data via `su`, but that could introduce other issues with migrations needing root-level access

## Testing Notes

- Verify on Unraid/Synology with PUID/PGID set
- Test LLM provider "Fetch Models" flow after container restart
- Confirm cache files are created with correct ownership (PUID:PGID)
