# Meilisearch Integration Roadmap

Full-text search implementation using Meilisearch and Laravel Scout.

**All phases complete (2026-01-30):** Docker setup, Scout/Meilisearch backend, User searchable, SearchService, SearchController, SearchAdminController, global search modal (Cmd+K), Configuration > Search admin page, recipe and patterns for adding searchable models. **Model expansion (2026-01-30):** Notification, EmailTemplate, ApiToken, AIProvider, Webhook searchable; index settings configured; admin search settings (results per page, suggestions limit, min query length) in Configuration > Search. **Phase 7 (2026-01-30):** Page content search (config/search-pages.php, Meilisearch `pages` index), UserGroup searchable, getSuggestions returns pages + groups + users, add-searchable-page recipe, patterns and roadmap updated.

## Overview

Integrate Meilisearch as the search engine for Sourdough, providing fast full-text search across all searchable content. Uses Laravel Scout for backend integration and provides a unified search experience in the frontend.

**Why Meilisearch:**
- Typo-tolerant, instant search results
- Easy to deploy (single binary or Docker container)
- Great Laravel Scout integration
- Low resource usage compared to Elasticsearch
- Built-in filtering, faceting, and highlighting

## Prerequisites

- Docker setup complete (ADR-009)
- Database migrations stable

## Phase 1: Docker Setup

Meilisearch runs **embedded in the main app container** (managed by Supervisor), not as a separate container. This aligns with ADR-009 single-container architecture.

- [x] Add Meilisearch binary to Dockerfile and run via Supervisor
- [x] Add Meilisearch volume for data persistence (`meilisearch_data` → `/var/lib/meilisearch`)
- [x] Configure environment variables (`MEILI_MASTER_KEY`, `MEILI_ENV`)
- [x] Update `docs/docker.md` with Meilisearch configuration
- [x] Test container startup and connectivity

**Key files:**
- `docker/Dockerfile` (Meilisearch binary install)
- `docker/supervisord.conf` (meilisearch program)
- `docker/entrypoint.sh` (Meilisearch data directory setup)
- `docker-compose.yml` (app volumes: `meilisearch_data`)
- `docker-compose.prod.yml`
- `.env.example`

## Phase 2: Backend Integration

Install and configure Laravel Scout with Meilisearch driver.

- [x] Install Laravel Scout: `composer require laravel/scout`
- [x] Install Meilisearch driver: `composer require meilisearch/meilisearch-php`
- [x] Configure Scout for Meilisearch in `config/scout.php`
- [x] Add Scout environment variables to `.env.example`
- [x] Create `SearchService` for centralized search logic
- [x] Add search configuration to settings system (admin-configurable)

**Environment variables:**
```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-master-key
```
(Meilisearch runs inside the app container on 127.0.0.1:7700.)

**Key files:**
- `backend/config/scout.php`
- `backend/app/Services/Search/SearchService.php`
- `backend/composer.json`

## Phase 3: Model Configuration

Make models searchable with Scout.

- [x] Add `Searchable` trait to User model
- [x] Configure searchable attributes for User
- [x] Add `Searchable` trait to any other content models (Notification, EmailTemplate, ApiToken, AIProvider, Webhook)
- [x] Configure index settings (filterable attributes, sortable attributes)
- [x] Create artisan command for bulk indexing: `php artisan search:reindex`
- [x] Add model observers for automatic index updates (Scout trait handles create/update/delete)
- [x] Test search index synchronization

**Example model configuration:**
```php
use Laravel\Scout\Searchable;

class User extends Authenticatable
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    public function searchableAs(): string
    {
        return 'users';
    }
}
```

**Key files:**
- `backend/app/Models/User.php`
- `backend/app/Models/*.php` (other searchable models)

## Phase 4: API Endpoints

Create search API for frontend consumption.

- [x] Create `SearchController` with global search endpoint
- [x] Add endpoint for searching specific model types
- [x] Implement search filters (type=users)
- [x] Add pagination support for search results
- [x] Implement search highlighting in results
- [x] Add search suggestions/autocomplete endpoint
- [x] Create API tests for search endpoints
- [x] Document search API in `docs/api-reference.md`

**API endpoints:**
```
GET /api/search?q={query}&type={type}&page={page}&per_page={n}
GET /api/search/suggestions?q={query}&limit={n}
```
Both routes use `log.access:User` middleware (access logged when returning user data).

**Key files:**
- `backend/app/Http/Controllers/Api/SearchController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/SearchTest.php`

## Phase 5: Frontend Integration

Build search UI components.

- [x] Create search service in frontend for API calls (`frontend/lib/search.ts`)
- [x] Debounced input in SearchModal (Command component)
- [x] SearchModal with result highlighting
- [x] SearchModal (Cmd+K) for global search via SearchProvider
- [x] Add search button to header/navigation
- [x] Implement search result type icons (`search-result-icon.tsx`)
- [x] Add keyboard navigation for search results (cmdk)
- [x] Add recent searches (localStorage)
- [x] Add "no results" state with suggestions
- [x] Search UX in modal (responsive)

**Key files:**
- `frontend/lib/search.ts`
- `frontend/components/search/search-modal.tsx`
- `frontend/components/search/search-provider.tsx`
- `frontend/components/search/search-result-icon.tsx`
- `frontend/components/header.tsx`

## Phase 6: Admin Configuration

Add admin settings for search configuration.

- [x] Add search settings to admin panel (Configuration > Search)
- [ ] Configure which models are searchable (toggle) — future
- [x] Set search result limits (results per page, suggestions limit, min query length via Configuration > Search Settings)
- [x] Rebuild index button in admin (per model and Reindex all)
- [x] View index statistics
- [ ] Configure search synonyms (optional)

**Key files:**
- `frontend/app/(dashboard)/configuration/search/page.tsx`
- `backend/app/Http/Controllers/Api/Admin/SearchAdminController.php`

## Phase 7: Page Content Search and UserGroup

Index static navigation pages in Meilisearch with rich content so users can find pages by features/providers (e.g. "AWS" finds AI/LLM and Storage pages). Add UserGroup as a searchable model for admin user management.

- [x] Create `config/search-pages.php` with all 24 pages and rich content keywords
- [x] Add `syncPagesToIndex()` to SearchService
- [x] Add `searchPages()` method (filter by admin_only for non-admins)
- [x] Add Searchable trait to UserGroup model
- [x] Add `searchUserGroups()` method (admin only)
- [x] Update `getSuggestions()` to include pages and groups
- [x] Update `search:reindex` command for pages and user_groups
- [x] Add pages and user_groups to admin search stats and reindex
- [x] Create [add-searchable-page.md](../../ai/recipes/add-searchable-page.md) recipe
- [x] Update [add-searchable-model.md](../../ai/recipes/add-searchable-model.md) with UserGroup example
- [x] Update patterns documentation

**Key files:**
- `backend/config/search-pages.php`
- `backend/app/Models/UserGroup.php`
- `backend/app/Services/Search/SearchService.php`
- `backend/app/Console/Commands/SearchReindexCommand.php`
- `frontend/components/search/search-modal.tsx`
- `frontend/components/search/search-result-icon.tsx`

## Success Criteria

- [x] Meilisearch runs inside the app container (Phase 1)
- [x] Models indexed automatically on create/update/delete (Scout trait on User)
- [x] Search returns results via API (Phase 4)
- [x] Cmd+K opens global search modal (Phase 5)
- [x] Search supports typo tolerance (Meilisearch)
- [x] Admin can rebuild indexes from UI (Phase 6; CLI `search:reindex` exists)
- [x] Search documented in API reference and recipe for adding searchable models

## Post-Implementation Notes

- **Access logging (HIPAA):** `GET /api/search` and `GET /api/search/suggestions` use `log.access:User` middleware because they return user data (name, email, id). See [Logging Compliance](../../.cursor/rules/logging-compliance.mdc).
- **SearchModal race condition:** The modal uses a ref (`latestQueryRef`) so that only the response matching the current query updates results; stale responses are ignored. Loading is always cleared in `finally`.

## Future Enhancements

- Federated search across multiple indexes
- Search analytics (popular queries, zero-result queries)
- Advanced filters (faceted search)
- Search result ranking customization
- Multi-tenancy support (scoped indexes)

## Implementation Journal

- [Meilisearch Integration (Phases 1-3)](../journal/2026-01-30-meilisearch-phases-1-3.md)
- [Meilisearch Integration (Phases 4-6)](../journal/2026-01-30-meilisearch-phases-4-6.md)
- [Meilisearch Configuration](../journal/2026-01-30-meilisearch-configuration.md)
- [Meilisearch Embedded in Container](../journal/2026-01-30-meilisearch-embedded.md)
- [Alpine to Debian Migration for Meilisearch](../journal/2026-01-30-alpine-to-debian-meilisearch.md)
- [Meilisearch Production Permission Denied Fix](../journal/2026-02-05-meilisearch-production-permissions.md)
- [Search Reindex Bugfixes](../journal/2026-02-05-search-reindex-bugfixes.md)
