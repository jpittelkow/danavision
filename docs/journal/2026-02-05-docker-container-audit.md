# Docker Container Audit - 2026-02-05

## Overview

Completed the Docker Container Audit per [docker-audit-roadmap.md](../plans/docker-audit-roadmap.md). Implemented security, correctness, and documentation updates across the Docker stack.

## Implementation Approach

- **Phase 1:** Verified baseline with `docker-compose build --no-cache` (succeeded).
- **Phase 2 – Dockerfile:** Pinned Meilisearch to a specific version via GitHub release asset (`MEILISEARCH_VERSION=1.6.2`) instead of `curl | sh`; removed default `MEILI_MASTER_KEY` from image.
- **Phase 3 – Scripts:** Added `set -e` to `start-nextjs.sh`; added production check in `entrypoint.sh` so container exits if `MEILI_MASTER_KEY` is unset in production.
- **Phase 4 – Nginx:** Removed deprecated `X-XSS-Protection` header from `docker/nginx.conf`.
- **Phase 5 – Compose:** Removed deprecated `version: '3.8'` from both compose files; added `SANCTUM_STATEFUL_DOMAINS`, `PUID`, and `PGID` to `docker-compose.prod.yml`.
- **Phase 6 – Docs:** Updated `docs/docker.md` (security headers table, Meilisearch version pinning, SANCTUM_STATEFUL_DOMAINS, Unraid example); moved Docker Container Audit to Completed in `docs/roadmaps.md`.
- **Phase 7:** Full clean rebuild succeeded; container starts. Fresh start with empty volumes hits existing migration order issue (user_groups must exist before `remove_is_admin` migration), unrelated to audit changes.

## Observations

- Meilisearch install is now reproducible and auditable via `MEILISEARCH_VERSION` build arg.
- Production images no longer bake a default master key; entrypoint enforces `MEILI_MASTER_KEY` in production.
- Nginx security headers align with current browser behavior (X-XSS-Protection removed).

## Testing Notes

- `docker-compose build --no-cache` and `docker-compose up -d` succeed.
- With existing data volumes, application should reach healthy state; with empty volumes, run migrations in correct order or use a pre-seeded DB for full verification.
