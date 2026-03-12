# Alpine to Debian Migration for Meilisearch - 2026-01-30

## Overview

Migrated the Docker base images from Alpine Linux to Debian to enable embedded Meilisearch. Meilisearch binaries are compiled against glibc, but Alpine uses musl libc, causing exit code 127 when running the official install script's binaries. Debian-based images provide glibc and full Meilisearch compatibility.

## Implementation Approach

- **Base images:** Changed `node:20-alpine` to `node:20-slim` (frontend builder) and `php:8.3-fpm-alpine` to `php:8.3-fpm` (production).
- **Package manager:** Replaced `apk add` with `apt-get update && apt-get install -y` and mapped Alpine package names to Debian equivalents (e.g., `sqlite-dev` → `libsqlite3-dev`, `libjpeg-turbo-dev` → `libjpeg-dev`).
- **Meilisearch install:** Added `curl -L https://install.meilisearch.com | sh` in `/tmp`, moved binary to `/usr/local/bin/meilisearch`.
- **Supervisor:** Uncommented the `[program:meilisearch]` section and set `command=/usr/local/bin/meilisearch`.
- **Compose:** Changed `SCOUT_DRIVER=null` to `SCOUT_DRIVER=meilisearch` in `docker-compose.yml`.

## Challenges Encountered

- Alpine package names differ from Debian; required mapping for all build dependencies (sqlite, jpeg, freetype, oniguruma, icu, postgresql).
- The Meilisearch install script writes the binary to the current directory; used `cd /tmp` before running to ensure a writable location.

## Observations

- Debian images are larger than Alpine (~400MB vs ~150MB base); acceptable trade-off for working search.
- Single-container architecture (ADR-009) preserved; Meilisearch runs as a Supervisor-managed process inside the app container.
- All documentation updated to reflect the working setup and remove Alpine/musl incompatibility notes.

## Trade-offs

- **Image size increase:** Debian images add ~200–250MB to the final image.
- **Build time:** Slightly longer due to larger base image download on first build.
- **Benefit:** Full Meilisearch search with typo-tolerance, instant results, and all Scout integration features.

## Files Changed

| File | Changes |
|------|---------|
| `docker/Dockerfile` | Base images to Debian, apt-get packages, Meilisearch install |
| `docker/supervisord.conf` | Uncommented Meilisearch program |
| `docker-compose.yml` | `SCOUT_DRIVER=meilisearch`, removed disabled note |
| `docs/docker.md` | Verification commands, Debian note |
| `docs/plans/meilisearch-integration-roadmap.md` | Phase 1 test item complete |
| `docs/adr/009-docker-single-container.md` | Dockerfile example to Debian |
| `.cursor/rules/local-docker-development.mdc` | Meilisearch commands, meilisearch_data volume |

## Testing Verification

After migration, verify:

1. Build: `docker-compose build --no-cache`
2. Start: `docker-compose up -d`
3. Logs: `docker-compose logs -f` (Meilisearch should appear in startup)
4. Health: `docker-compose exec app curl -f http://127.0.0.1:7700/health`
5. Search: Use Cmd+K or `GET /api/search?q=test`
6. Reindex: `docker-compose exec app php /var/www/html/backend/artisan search:reindex`

## Next Steps (Future Considerations)

- Consider pinning Meilisearch version in Dockerfile for reproducible builds.
- Monitor image size; document if optimization is needed for constrained environments.
