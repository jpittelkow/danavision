# ADR-021: Search with Meilisearch Integration

## Status

Accepted

## Date

2026-01-31

## Context

Sourdough needs a fast, full-text search capability for:
- Searching across multiple models (users, notifications, templates, etc.)
- Global search (command palette style) for quick navigation
- Static page search for help/documentation navigation
- Self-hosted deployment without external search services

The search must work out-of-the-box in the single Docker container with minimal configuration.

## Decision

We implement **Meilisearch** as the search engine, embedded in the Docker container with Laravel Scout for model indexing.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   Search Architecture                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Frontend                                                        │
│  ┌─────────────────────────────────────┐                        │
│  │  SearchModal / SearchProvider       │                        │
│  │  - Global keyboard shortcut (⌘K)    │                        │
│  │  - Federated search across types    │                        │
│  └───────────────┬─────────────────────┘                        │
│                  │                                               │
│                  ▼                                               │
│  Backend                                                         │
│  ┌─────────────────────────────────────┐                        │
│  │  SearchController                   │                        │
│  │  GET /api/search?q=...              │                        │
│  └───────────────┬─────────────────────┘                        │
│                  │                                               │
│                  ▼                                               │
│  ┌─────────────────────────────────────┐                        │
│  │  SearchService                      │                        │
│  │  - Model search via Scout           │                        │
│  │  - Static page search               │                        │
│  │  - Result aggregation               │                        │
│  └───────────────┬─────────────────────┘                        │
│                  │                                               │
│                  ▼                                               │
│  ┌─────────────────────────────────────┐                        │
│  │  Meilisearch (127.0.0.1:7700)       │                        │
│  │  - Embedded in Docker container     │                        │
│  │  - Data at /var/lib/meilisearch     │                        │
│  └─────────────────────────────────────┘                        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Searchable Models

Models implement the `Searchable` trait from Laravel Scout:

| Model | Index Name | Searchable Fields |
|-------|------------|-------------------|
| User | `users` | name, email |
| UserGroup | `user_groups` | name, slug, description |
| Notification | `notifications` | title, body |
| EmailTemplate | `email_templates` | key, name, subject |
| NotificationTemplate | `notification_templates` | type, title, body |
| ApiToken | `api_tokens` | name |
| AIProvider | `ai_providers` | name, provider |
| Webhook | `webhooks` | name, url |

### Static Page Search

Non-model content (configuration pages) is indexed separately:

```php
// config/search-pages.php
return [
    'pages' => [
        [
            'path' => '/configuration/email',
            'title' => 'Email Settings',
            'description' => 'Configure SMTP, email providers',
            'keywords' => ['smtp', 'mailgun', 'sendgrid'],
        ],
        // ... more pages
    ],
];
```

### Search API

```
GET /api/search?q={query}&types[]={type}

Response:
{
  "results": {
    "users": [...],
    "pages": [...],
    "notifications": [...]
  },
  "query": "search term",
  "took_ms": 12
}
```

### Indexing Strategy

- **Automatic**: Scout observers sync models on create/update/delete
- **Manual reindex**: `php artisan search:reindex` for full rebuild
- **Selective reindex**: `php artisan search:reindex users` for single model

### Configuration

```php
// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY', ''),
    ],
];
```

### Docker Integration

Meilisearch runs as a Supervisor-managed process within the container:
- Listens on `127.0.0.1:7700` (not exposed externally)
- Data persisted in `/var/lib/meilisearch` volume
- Starts before PHP-FPM to ensure availability

## Consequences

### Positive

- Fast, typo-tolerant full-text search out-of-the-box
- No external services required for self-hosted deployments
- Scout integration makes model indexing automatic
- Federated search provides unified search experience
- Static page search enables command palette navigation

### Negative

- Meilisearch adds ~100MB to container size
- Requires glibc (Debian base image, not Alpine)
- Index rebuilds needed after major data changes
- Memory usage increases with index size

### Neutral

- Search is scoped to authenticated user's accessible data
- External Meilisearch can be used by overriding `MEILISEARCH_HOST`
- Index settings can be customized per model

## Key Files

- `backend/app/Services/Search/SearchService.php` - Search orchestration
- `backend/app/Http/Controllers/Api/SearchController.php` - Search API
- `backend/config/scout.php` - Scout configuration
- `backend/config/search-pages.php` - Static page definitions
- `backend/app/Console/Commands/SearchReindexCommand.php` - Reindex command
- `backend/app/Models/*.php` - Searchable trait on models
- `frontend/components/search/search-provider.tsx` - Global search context
- `frontend/components/search/search-modal.tsx` - Search UI modal
- `frontend/lib/search.ts` - Search API client
- `frontend/lib/search-pages.ts` - Page search utilities
- `frontend/app/(dashboard)/configuration/search/page.tsx` - Admin search config

## API Documentation

- [Meilisearch Documentation](https://www.meilisearch.com/docs)
- [Laravel Scout](https://laravel.com/docs/12.x/scout)

## Related Decisions

- [ADR-009: Docker Single-Container Architecture](./009-docker-single-container.md) - Meilisearch embedded in container
- [ADR-010: Database Abstraction Strategy](./010-database-abstraction.md) - Scout works with all supported databases

## Implementation Journal

- [Search Documentation Update (2026-01-30)](../journal/2026-01-30-search-documentation-update.md)
- [Meilisearch Phases 1-3 (2026-01-30)](../journal/2026-01-30-meilisearch-phases-1-3.md)
- [Meilisearch Phases 4-6 (2026-01-30)](../journal/2026-01-30-meilisearch-phases-4-6.md)
- [Meilisearch Configuration (2026-01-30)](../journal/2026-01-30-meilisearch-configuration.md)
- [Meilisearch Embedded (2026-01-30)](../journal/2026-01-30-meilisearch-embedded.md)
- [Alpine to Debian Meilisearch (2026-01-30)](../journal/2026-01-30-alpine-to-debian-meilisearch.md)
- [Meilisearch Production Permissions (2026-02-05)](../journal/2026-02-05-meilisearch-production-permissions.md)
- [Search Reindex Bugfixes (2026-02-05)](../journal/2026-02-05-search-reindex-bugfixes.md)
