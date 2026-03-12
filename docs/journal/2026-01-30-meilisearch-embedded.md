# Meilisearch Embedded in Container - 2026-01-30

## Overview

Meilisearch was moved from a separate Docker container into the main application container. It now runs as a Supervisor-managed process alongside Nginx, PHP-FPM, Next.js, and the Laravel queue/scheduler. This aligns with ADR-009 (Docker Single-Container Architecture) and simplifies deployment: one container, one `docker run`, no `depends_on` or multi-service orchestration for search.

## Implementation Approach

- **Dockerfile:** Install Meilisearch binary via the official install script (`curl -L https://install.meilisearch.com | sh`), place in `/usr/local/bin`, and create `/var/lib/meilisearch` with correct ownership.
- **Supervisor:** Added a `[program:meilisearch]` section with `--db-path /var/lib/meilisearch/data`, `--http-addr 127.0.0.1:7700`, and `priority=1` so it starts before other services.
- **Entrypoint:** Create and chown `/var/lib/meilisearch/data` at startup so the volume mount or default path is ready for Meilisearch.
- **Compose:** Removed the `meilisearch` service from both dev and prod compose files. App service now mounts `meilisearch_data:/var/lib/meilisearch` and uses `MEILISEARCH_HOST=http://127.0.0.1:7700`, `MEILI_ENV`, and `MEILI_MASTER_KEY` so Scout and the Meilisearch process both have the correct config.

No “wait for Meilisearch” step was added in the entrypoint because Supervisor starts after the entrypoint completes. SearchService already falls back to database search when Meilisearch is unavailable, so brief unavailability during container startup is acceptable.

## Challenges Encountered

- **Startup order:** Migrations run in the entrypoint before Supervisor (and thus Meilisearch) is started. We did not add a health-wait loop in the entrypoint because it would require starting Supervisor earlier or running Meilisearch separately. Relying on SearchService fallback and normal Supervisor startup order keeps the implementation simple.
- **Supervisor env substitution:** Meilisearch is started with `%(ENV_MEILI_ENV)s` and `%(ENV_MEILI_MASTER_KEY)s` so the same env vars passed to the app container are used by the Meilisearch process.

## Observations

- Single-container deployment is easier to document and operate (one image, one set of logs, one health check).
- The `meilisearch_data` volume remains in the compose `volumes` section and is mounted on the app service at `/var/lib/meilisearch`, so index data persists across restarts.
- Removing `MEILI_PORT` from `.env.example` is correct; Meilisearch is no longer exposed on the host.

## Trade-offs

- **Larger image:** The Meilisearch binary adds to the image size versus using a separate Meilisearch image.
- **Shared resources:** Meilisearch shares CPU/memory with the rest of the app; for very large search workloads, a dedicated container could be considered later.
- **Simplicity:** One container to build, ship, and backup is a clear win for the target self-hosted use case.

## Next Steps (Future Considerations)

- Optional: Pin Meilisearch version in the Dockerfile (e.g. specific release URL) for reproducible builds.
- If search scale out is ever needed, document how to run Meilisearch as a separate container again and set `MEILISEARCH_HOST` to that service.

## Testing Notes

- Run `docker-compose up -d --build` and confirm Meilisearch appears in `docker-compose logs -f app`.
- From inside the app container: `curl -f http://127.0.0.1:7700/health`.
- Exercise global search (e.g. Cmd+K) and Configuration > Search reindex to confirm Scout and Meilisearch work end-to-end.
- Restart the container and verify search index data persists (volume intact).
