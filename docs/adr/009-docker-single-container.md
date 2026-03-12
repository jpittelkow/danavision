# ADR-009: Docker Single-Container Architecture

## Status

Accepted

## Date

2026-01-24

## Context

Sourdough targets self-hosted deployments where simplicity is paramount. Users should be able to:
- Deploy with a single `docker run` command
- Avoid complex multi-container orchestration
- Have all services running with minimal configuration
- Update easily with image pulls

We need to balance simplicity with maintainability and production readiness.

## Decision

We will package all services in a **single Docker container** using Supervisor for process management.

### Container Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                      Single Docker Container                          │
│                       (sourdough:latest)                              │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │                       Supervisor                                │  │
│  │              (Process Manager - PID 1)                          │  │
│  └──────────────────────┬─────────────────────────────────────────┘  │
│    ┌────────┬───────┬───┼────┬────────┬──────────┬──────────┐       │
│    ▼        ▼       ▼   ▼    ▼        ▼          ▼          ▼       │
│  ┌──────┐┌──────┐┌──────┐┌──────┐┌─────────┐┌────────┐┌──────────┐ │
│  │Nginx ││PHP-  ││ Node ││Queue ││Schedule││ Reverb ││Meili-  │ │
│  │ :80  ││FPM   ││:3000 ││×2    ││(cron)  ││ :6001  ││srch:7700│ │
│  └──────┘└──────┘└──────┘└──────┘└─────────┘└────────┘└──────────┘ │
│    │        │       │       │                                        │
│    └────────┴───────┴───────┘                                        │
│      ▼                                                               │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │                        Volumes                                  │  │
│  │  /data  /data/backups  /var/lib/meilisearch (search)            │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

### Process Management

Supervisor manages eight processes (seven persistent services plus one initialization task):

1. **Nginx** - Reverse proxy, static files, rate limiting
2. **PHP-FPM** - Laravel API execution
3. **Node** - Next.js frontend (production build)
4. **Queue Worker** - Background job processing (`numprocs=2`)
5. **Scheduler** - Laravel cron-based task scheduling
6. **Reverb** - WebSocket server for real-time broadcasting (:6001, internal)
7. **Meilisearch** - Search engine (listens on 127.0.0.1:7700, data in `/var/lib/meilisearch`)
8. **Search Reindex** - One-shot initialization task for Meilisearch indexing

```ini
# supervisord.conf (excerpt)
[supervisord]
nodaemon=true
user=root

[program:meilisearch]
command=/usr/local/bin/meilisearch --db-path /var/lib/meilisearch/data --http-addr 127.0.0.1:7700
autorestart=true
priority=1
user=www-data
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

[program:nginx]
command=nginx -g "daemon off;"
autorestart=true
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

[program:php-fpm]
command=php-fpm --nodaemonize
autorestart=true
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

[program:node]
command=node /app/frontend/.next/standalone/server.js
autorestart=true
environment=PORT="3000",HOSTNAME="0.0.0.0"
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

[program:queue]
command=php /app/backend/artisan queue:work --sleep=3 --tries=3
autorestart=true
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name _;
    
    # Frontend (Next.js)
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
    
    # Backend API
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME /app/backend/public$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Multi-Stage Build

The production image uses Debian (not Alpine) because embedded Meilisearch requires glibc; Alpine's musl is incompatible with Meilisearch binaries.

```dockerfile
# Stage 1: Build frontend
FROM node:20-alpine AS frontend-builder
WORKDIR /build
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Stage 2: Build backend dependencies
FROM composer:2 AS backend-builder
WORKDIR /build
COPY backend/composer.* ./
RUN composer install --no-dev --optimize-autoloader

# Stage 3: Production image (Debian for Meilisearch/glibc)
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends nginx supervisor nodejs npm sqlite3 curl \
    && rm -rf /var/lib/apt/lists/*

# Copy application
COPY --from=backend-builder /build/vendor /app/backend/vendor
COPY backend/ /app/backend/
COPY --from=frontend-builder /build/.next/standalone /app/frontend/.next/standalone
COPY --from=frontend-builder /build/.next/static /app/frontend/.next/static
COPY --from=frontend-builder /build/public /app/frontend/public

# Copy configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/entrypoint.sh /entrypoint.sh

# Create data directory
RUN mkdir -p /data && chown -R www-data:www-data /data

EXPOSE 80
VOLUME ["/data"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### Entrypoint Script

The entrypoint handles database setup, migrations, config caching, and secret key management before handing off to Supervisor.

#### APP_KEY Management

`APP_KEY` is never baked into the image. It is resolved at runtime in this priority order:

1. **`APP_KEY` env var provided** — used directly, written into `.env`
2. **Data volume file exists** (`/var/www/html/data/.app_key`) — loaded from persistent storage
3. **Neither** — generated via `php artisan key:generate --show`, saved to the data volume file

On first boot (scenario 3), the entrypoint logs instructions to retrieve the key:

```
==========================================
  IMPORTANT: APP_KEY has been generated.
  Back it up with:
    docker exec sourdough cat /var/www/html/data/.app_key
  Then set APP_KEY in your environment to
  avoid data loss if the volume is lost.
==========================================
```

The key itself is **never printed to logs** — only the retrieval command. This prevents accidental exposure via log aggregation services (Datadog, CloudWatch, Loki, etc.).

If `APP_KEY` is provided via env var but differs from the saved volume key (e.g. after key rotation or accidental change), a warning is logged at boot:

```
WARNING: APP_KEY differs from the key saved in the data volume.
  This may cause decryption failures for existing encrypted data.
```

**Backup recommendation:** After first boot, run `docker exec sourdough cat /var/www/html/data/.app_key` and store the key securely. Set it as `APP_KEY` in your environment so it survives volume loss.

### Volume Strategy

| Volume | Purpose | Default Location |
|--------|---------|------------------|
| `/data` | All persistent data | Named volume |
| `/data/database.sqlite` | SQLite database | Inside /data |
| `/data/storage` | Uploaded files | Inside /data |
| `/data/backups` | Backup files | Inside /data |
| `/var/lib/meilisearch` | Meilisearch index data | Named volume `meilisearch_data` |

### docker-compose Files

Development:
```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - appdata:/data
      - ./backend:/app/backend  # Hot reload
      - ./frontend:/app/frontend
    environment:
      - APP_ENV=local
      - APP_DEBUG=true

volumes:
  appdata:
```

Production:
```yaml
# docker-compose.prod.yml
version: '3.8'
services:
  app:
    image: ghcr.io/sourdough-start/sourdough:latest
    ports:
      - "80:80"
    volumes:
      - appdata:/data
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      # APP_KEY is auto-generated on first boot and persisted in the data volume.
      # Set this only if migrating an existing deployment or after backing up the generated key.
      # - APP_KEY=${APP_KEY}
    restart: unless-stopped

volumes:
  appdata:
```

## Consequences

### Positive

- Single command deployment (`docker run`)
- No container orchestration required
- All logs in one place
- Simple backup (single volume)
- Easy version updates

### Negative

- Cannot scale individual components
- All processes share resources
- Larger image size than split containers
- Supervisor adds slight complexity

### Neutral

- Suitable for small to medium deployments
- Large deployments can split containers if needed
- Health checks must monitor all processes

## Related Decisions

- [ADR-001: Technology Stack](./001-technology-stack.md)
- [ADR-010: Database Abstraction Strategy](./010-database-abstraction.md)

## Notes

### Health Check

```dockerfile
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
  CMD curl -f http://localhost/api/health || exit 1
```

The health endpoint returns a simple `{"status": "ok"}` response confirming the PHP-FPM and Nginx stack is responsive. It does not perform deep checks (database, queue, disk) — those are monitored separately via the audit and application logging systems.

### Production Safety

From DanaVision, we include production safety measures:
- Prevent accidental database deletion
- Require confirmation for destructive operations
- Backup before major operations

## Implementation Journal

- [Docker Next.js Volume Fix (2026-01-26)](../journal/2026-01-26-docker-nextjs-volume-fix.md)
- [Docker Container Audit (2026-02-05)](../journal/2026-02-05-docker-container-audit.md)
- [Docker Optimization and Security Updates (2026-02-05)](../journal/2026-02-05-docker-optimization-and-security-updates.md)
- [Alpine to Debian Meilisearch (2026-01-30)](../journal/2026-01-30-alpine-to-debian-meilisearch.md)
- [Migration Service Container Fix (2026-02-05)](../journal/2026-02-05-migration-service-container-fix.md)
- [Cache Permissions Fix (2026-02-05)](../journal/2026-02-05-cache-permissions-fix.md)
- [Meilisearch Production Permissions (2026-02-05)](../journal/2026-02-05-meilisearch-production-permissions.md)
