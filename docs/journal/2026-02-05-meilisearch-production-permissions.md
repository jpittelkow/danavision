# Meilisearch Production Permission Denied Fix - 2026-02-05

## Overview

Fixed a production bug where Meilisearch failed to start automatically, crashing immediately with `Permission denied (os error 13)` and entering Supervisor's `FATAL` state after exhausting retries. Search was completely unavailable until manually resolved.

## Root Cause

Two related issues in the Meilisearch Supervisor configuration:

1. **Missing working directory:** The `[program:meilisearch]` section in `supervisord.conf` did not set a `directory=` directive. Supervisor defaults the working directory to `/`. On startup, Meilisearch (running as www-data) attempts to create its default `dumps/` and `snapshots/` directories relative to CWD — writing to `/dumps/` is denied for non-root users.

2. **Missing dump/snapshot directories:** The entrypoint only created `/var/lib/meilisearch/data` but not the `dumps` and `snapshots` subdirectories that Meilisearch expects.

This only affected **production** because:
- In development (`MEILI_ENV=development`), Meilisearch may skip certain directory creation
- The Unraid deployment uses PUID/PGID (99:100) which remaps www-data, adding permission complexity

## Implementation Approach

- **`docker/supervisord.conf`**: Added `directory=/var/lib/meilisearch` to the meilisearch program, and added explicit `--dump-dir /var/lib/meilisearch/dumps --snapshot-dir /var/lib/meilisearch/snapshots` flags to the command.
- **`docker/entrypoint.sh`**: Extended the Meilisearch setup to create `dumps` and `snapshots` subdirectories alongside `data`, and added `chmod -R 755` to ensure correct permission bits (not just ownership).

## Challenges Encountered

- The Meilisearch error message (`Permission denied (os error 13)`) does not indicate which file or directory is inaccessible, making root cause analysis difficult.
- The issue only manifests in production mode with Supervisor — not when running Meilisearch manually or in development.

## Observations

- This is the third Docker permission-related fix (after Meilisearch HOME issue and cache permissions fix). The pattern is: processes running as www-data need carefully configured working directories and HOME.
- The `nextjs` program already had `directory=/var/www/html/frontend` set — meilisearch was the outlier.
- Adding explicit `--dump-dir` and `--snapshot-dir` flags is defensive: even if Meilisearch changes its default behavior, the paths are guaranteed writable.

## Trade-offs

- Explicit flags make the supervisor command longer but remove implicit CWD dependency.
- The `chmod -R 755` on the Meilisearch directory is slightly more restrictive than the 775 used elsewhere (storage, etc.), which is appropriate since only www-data needs access.

## Testing Notes

- Restart production container and verify Meilisearch enters RUNNING state (not FATAL)
- Check `curl http://127.0.0.1:7700/health` returns healthy status
- Verify search works (Cmd+K or header search) after container restart
- Test on Unraid with PUID=99 PGID=100
