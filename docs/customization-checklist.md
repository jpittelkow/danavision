# Customization Checklist

When using Sourdough as a base for a new project, work through this checklist systematically. Each section builds on the previous ones.

## 1. Identity & Branding

Update references to "Sourdough" throughout the project:

- [ ] **App name and short name** in [.env.example](../.env.example):
  ```env
  NEXT_PUBLIC_APP_NAME=YourAppName
  NEXT_PUBLIC_APP_SHORT_NAME=YA
  ```

- [ ] **Short name constant** in [frontend/config/app.ts](../frontend/config/app.ts):
  ```typescript
  shortName: process.env.NEXT_PUBLIC_APP_SHORT_NAME || 'YA',
  ```

- [ ] **README.md** - Replace project description, getting started, and repository URLs

- [ ] **VERSION** - Reset to `0.1.0` or your starting version

- [ ] **CHANGELOG.md** - Clear history or start fresh with your first entry

- [ ] **LICENSE** - Update copyright holder; change license type if needed

- [ ] **package.json files** (optional):
  - `frontend/package.json` - Update `name` field
  - `backend/composer.json` - Update `name` field

## 2. Repository Setup

Configure your git repository:

- [ ] **Update git remote** (if you cloned rather than forked):
  ```bash
  git remote set-url origin https://github.com/your-username/your-repo.git
  ```

- [ ] **Optionally reset git history** (start fresh):
  ```bash
  rm -rf .git
  git init
  git add .
  git commit -m "Initial commit based on Sourdough"
  ```

- [ ] **Update GitHub workflow files** in `.github/workflows/` if needed (CI/CD configuration)

## 3. Docker & Environment

Configure the development environment:

- [ ] **Container name** in [.env.example](../.env.example):
  ```env
  CONTAINER_NAME=your-app-dev
  ```

- [ ] **Port** (if 8080 conflicts with other services):
  ```env
  APP_PORT=8081
  ```

- [ ] **Copy and configure your .env**:
  ```bash
  cp .env.example .env
  # Edit .env with your values
  ```

- [ ] **Review docker-compose.yml** - Update service names if desired (optional)

## 4. Optional Feature Removal

Remove features you don't need. For each feature, remove both backend and frontend code.

### LLM/AI Integration

If you don't need AI features:

- [ ] Delete `backend/app/Services/LLM/`
- [ ] Delete `backend/config/llm.php`
- [ ] Delete `backend/app/Http/Controllers/Api/LLMController.php`
- [ ] Delete `backend/app/Http/Controllers/Api/LLMModelController.php`
- [ ] Delete `backend/app/Services/LLMModelDiscoveryService.php`
- [ ] Delete `frontend/app/(dashboard)/configuration/ai/`
- [ ] Remove LLM routes from `backend/routes/api.php`
- [ ] Remove LLM-related migrations from `backend/database/migrations/`

### Notification Channels

Keep only the channels you need. For each unused channel:

- [ ] Delete individual channel files from `backend/app/Services/Notifications/Channels/`
  - Keep: `DatabaseChannel.php` (in-app notifications)
  - Keep: `EmailChannel.php` (likely needed)
  - Optional: `TelegramChannel.php`, `DiscordChannel.php`, `SlackChannel.php`, etc.

- [ ] Update `backend/config/notifications.php` to remove unused channels

### Backup Destinations

Keep only the destinations you need:

- [ ] Delete unused destination files from `backend/app/Services/Backup/Destinations/`
  - Keep: `LocalDestination.php` (always useful)
  - Optional: `S3Destination.php`, `SFTPDestination.php`, `GoogleDriveDestination.php`

- [ ] Update `backend/config/settings-schema.php` backup group to remove unused destination settings

### PWA Features

If you don't need Progressive Web App functionality:

- [ ] Delete `frontend/public/sw.js`
- [ ] Delete `frontend/public/offline.html`
- [ ] Delete `frontend/app/api/manifest/route.ts`
- [ ] Delete `frontend/lib/use-install-prompt.ts`
- [ ] Delete `frontend/lib/request-queue.ts`
- [ ] Delete `frontend/lib/web-push.ts`
- [ ] Delete `frontend/components/install-prompt.tsx`
- [ ] Remove PWA-related code from `frontend/components/app-shell.tsx`

### Search (Meilisearch)

If you don't need full-text search:

- [ ] Delete `backend/app/Services/Search/`
- [ ] Delete `backend/app/Http/Controllers/Api/SearchController.php`
- [ ] Delete `backend/app/Http/Controllers/Api/Admin/SearchAdminController.php`
- [ ] Delete `frontend/components/search/`
- [ ] Delete `frontend/lib/search.ts`
- [ ] Delete `frontend/lib/search-pages.ts`
- [ ] Remove search routes from `backend/routes/api.php`
- [ ] Remove Meilisearch from `docker/supervisord.conf`

## 5. Database

Adjust the database schema for your needs:

- [ ] **Review migrations** in `backend/database/migrations/`
  - Remove migrations for features you deleted
  - Keep core migrations (users, settings, etc.)

- [ ] **Plan your models** - What data will your app store?

- [ ] **Create your migrations**:
  ```bash
  docker-compose exec app php /var/www/html/backend/artisan make:model YourModel -m
  ```

- [ ] **Run migrations**:
  ```bash
  docker-compose exec app php /var/www/html/backend/artisan migrate:fresh
  ```

## 6. Search Configuration

If keeping search, update for your models:

- [ ] **Update searchable models** in `backend/app/Services/Search/SearchService.php`:
  - Add your models to `globalSearch()` method
  - Add transform methods for your models

- [ ] **Update page search** in `frontend/lib/search-pages.ts`:
  - Add your app's pages to the static page list
  - Update admin-only page checks

- [ ] **Add Searchable trait** to your models (see recipe: `docs/ai/recipes/add-searchable-model.md`)

## 7. Documentation

Update documentation for your project:

- [ ] **Clear or repurpose journal entries** in `docs/journal/`
  - Delete Sourdough-specific entries
  - Start your own development journal

- [ ] **Update roadmaps** in `docs/roadmaps.md`:
  - Remove completed Sourdough roadmaps
  - Add your planned features

- [ ] **Update overview** in `docs/overview.md`:
  - Change project description
  - Update feature list

- [ ] **Add your own recipes** to `docs/ai/recipes/`:
  - Document patterns specific to your app
  - Help future AI assistants understand your codebase

- [ ] **Update ADRs** as you make architecture decisions:
  - Add new ADRs for significant decisions
  - Reference them in `docs/architecture.md`

## 8. Configuration Pages

Update admin configuration for your app:

- [ ] **Review configuration navigation** in `frontend/app/(dashboard)/configuration/layout.tsx`:
  - Remove menu items for features you deleted
  - Add menu items for your features

- [ ] **Update dashboard widgets** in `frontend/app/(dashboard)/dashboard/page.tsx`:
  - Remove widgets for features you deleted
  - Add widgets relevant to your app

## 9. Final Verification

Verify everything works:

- [ ] **Rebuild and start**:
  ```bash
  docker-compose down
  docker-compose up -d --build
  ```

- [ ] **Create an account** at http://localhost:8080 (first user becomes admin)

- [ ] **Test core features**:
  - [ ] Login/logout works
  - [ ] Password reset works (if email configured)
  - [ ] Admin configuration pages load
  - [ ] Features you kept work correctly

- [ ] **Run backend tests**:
  ```bash
  docker-compose exec app php /var/www/html/backend/artisan test
  ```

- [ ] **Run E2E tests** (if Playwright configured):
  ```bash
  npx playwright test
  ```

- [ ] **Check for console errors** in browser dev tools

- [ ] **Verify search works** (if kept):
  ```bash
  docker-compose exec app php /var/www/html/backend/artisan search:reindex
  ```

## Quick Reference

### Common Commands

```bash
# Start development environment
docker-compose up -d

# Rebuild after Dockerfile changes
docker-compose up -d --build

# View logs
docker-compose logs -f

# Access container shell
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php /var/www/html/backend/artisan <command>

# Run migrations
docker-compose exec app php /var/www/html/backend/artisan migrate

# Run tests
docker-compose exec app php /var/www/html/backend/artisan test
```

### Key Files

| Purpose | Location |
|---------|----------|
| App configuration | `frontend/config/app.ts` |
| Environment template | `.env.example` |
| Docker config | `docker-compose.yml`, `docker/Dockerfile` |
| Backend routes | `backend/routes/api.php` |
| Frontend pages | `frontend/app/(dashboard)/` |
| Settings schema | `backend/config/settings-schema.php` |
| AI development guide | `docs/ai/README.md` |

### Getting Help

- Check `docs/ai/recipes/` for step-by-step guides
- Read relevant ADRs in `docs/adr/` for architecture context
- Look at similar existing implementations as examples
- Review `docs/ai/patterns/` for code style guidance
