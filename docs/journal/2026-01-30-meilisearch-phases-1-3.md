# Meilisearch Integration (Phases 1–3) - 2026-01-30

**Note:** The Phase 1 Docker setup (separate Meilisearch container) was superseded by the embedded approach; see [2026-01-30-meilisearch-embedded.md](2026-01-30-meilisearch-embedded.md).

## Overview

Implemented Meilisearch infrastructure and Laravel Scout integration for Sourdough (Phases 1–3 of the Meilisearch roadmap). Docker Compose runs Meilisearch alongside the app; the User model is searchable via Scout with a centralized SearchService and reindex command. API, frontend, and admin search UI are deferred to Phases 4–6.

## Implementation Approach

- **Phase 1 (Docker):** Added Meilisearch service to `docker-compose.yml` and `docker-compose.prod.yml` with healthcheck and `meilisearch_data` volume. App service depends on Meilisearch (healthy) and receives `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, `SCOUT_DRIVER`. Env vars in root and backend `.env.example`.
- **Phase 2 (Backend):** Added `laravel/scout` and `meilisearch/meilisearch-php` to composer; created `config/scout.php` (driver, meilisearch host/key). Introduced `App\Services\Search\SearchService` with `searchUsers()` (Scout search, DB fallback on failure, empty-query guard) and `reindexAll()` (calls `scout:import` for User).
- **Phase 3 (Models):** User model uses Scout `Searchable` trait with `toSearchableArray()` (id, name, email, is_admin, created_at) and `searchableAs()` => `users`. Added `search:reindex` artisan command (all or per-model). Feature tests cover User searchable config, SearchService pagination/reindex, and command success/failure.

## Challenges Encountered

- Composer/PHP not on PATH in the dev environment; packages were added to `composer.json` and config/code written so that `composer install` (e.g. in Docker) completes the setup.
- Tests run with default `SCOUT_DRIVER=collection` so they do not require a running Meilisearch instance.

## Observations

- Empty query guard in `searchUsers()` avoids hitting the search engine with an empty string and returns an ordered list instead.
- Fallback to database search when Meilisearch is unavailable keeps search working without Meilisearch (e.g. CI or disabled search).
- `search:reindex` is intentionally CLI-only for Phases 1–3; admin UI rebuild (Phase 6) can call the same SearchService.

## Trade-offs

- Search API and frontend (Cmd+K, etc.) are not implemented yet; Phases 4–6 will add endpoints and UI.
- Only User is searchable; other models can be added later by extending SearchService and the reindex command map.

## Next Steps (Future Considerations)

- Phase 4: SearchController, `GET /api/search`, filters, pagination, tests, API docs.
- Phase 5: Frontend search service, SearchInput/SearchResults/SearchModal (Cmd+K), keyboard nav, recent searches.
- Phase 6: Admin search settings, rebuild index button, index stats, optional synonyms.

## Testing Notes

- Run `php artisan test tests/Feature/SearchTest.php` (with Scout/Meilisearch packages installed).
- With Docker: `docker-compose up -d`, then `docker-compose exec app php /var/www/html/backend/artisan search:reindex` to populate the index after composer install.
