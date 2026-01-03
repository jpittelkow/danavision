# DanaVision Docker Documentation

## Overview

DanaVision is deployed as a single Docker container that includes everything needed to run the application:

- Nginx web server
- PHP-FPM 8.3
- SQLite database (embedded)
- Pre-built React frontend

## Quick Start

### Generate APP_KEY

```bash
docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

### Start with Docker Compose

```bash
# Edit docker-compose.yml with your APP_KEY
# Start the container
docker compose up -d
```

### Access the Application

- URL: http://localhost:8080
- Create an account on first visit

## Docker Compose Configurations

DanaVision has two Docker Compose configurations:

- **`docker-compose.yml`** - Development (database at `/var/www/html/data/`)
- **`docker-compose.prod.yml`** - Production (database at `/var/www/html/data/`)

> **Important:** Both use `/var/www/html/data/` for the SQLite database. We intentionally avoid `/var/www/html/database/` because mounting a volume there would overwrite Laravel's `database/migrations` folder, preventing migrations from running.

### Development (docker-compose.yml)

```yaml
services:
  danavision:
    build:
      context: .
      dockerfile: docker/Dockerfile
    image: ghcr.io/jpittelkow/danavision:latest
    container_name: danavision
    ports:
      - "8080:80"
    volumes:
      # Persistent data (dev uses /data directory)
      - danavision_data:/var/www/html/data
      - danavision_storage:/var/www/html/storage/app
      # Development: mount source code
      - ./backend/app:/var/www/html/app
      - ./backend/config:/var/www/html/config
      - ./backend/routes:/var/www/html/routes
      - ./backend/resources:/var/www/html/resources
      - ./backend/database/migrations:/var/www/html/database/migrations
    environment:
      - APP_NAME=${APP_NAME:-DanaVision}
      - APP_KEY=${APP_KEY}
      - APP_URL=${APP_URL:-http://localhost:8080}
      - APP_ENV=${APP_ENV:-local}
      - APP_DEBUG=${APP_DEBUG:-true}
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/var/www/html/data/database.sqlite
      - TZ=America/Chicago
      - SCHEDULE_TIMEZONE=America/Chicago
      - ALLOW_DB_INIT=${ALLOW_DB_INIT:-false}
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

volumes:
  danavision_data:
  danavision_storage:
```

### Production (docker-compose.prod.yml)

```yaml
services:
  danavision:
    image: ghcr.io/jpittelkow/danavision:latest
    container_name: danavision
    ports:
      - "8080:80"
    volumes:
      # Persistent data - uses /data to avoid overwriting database/migrations
      - danavision_data:/var/www/html/data
      - danavision_storage:/var/www/html/storage/app
    environment:
      - APP_NAME=${APP_NAME:-DanaVision}
      - APP_KEY=${APP_KEY}
      - APP_URL=${APP_URL:-http://localhost:8080}
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/var/www/html/data/database.sqlite
      - TZ=America/Chicago
      - SCHEDULE_TIMEZONE=America/Chicago
      - ALLOW_DB_INIT=${ALLOW_DB_INIT:-false}
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

volumes:
  danavision_data:
  danavision_storage:
```

> **Note:** First-time production setup requires `ALLOW_DB_INIT=true`:
> ```bash
> ALLOW_DB_INIT=true docker compose -f docker-compose.prod.yml up -d
> ```

## Dockerfile

The Dockerfile (in `docker/Dockerfile`) creates a single container with:

```dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    sqlite \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite

# Copy application files
COPY backend/ /var/www/html/

# Install dependencies
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader
RUN npm install && npm run build

# Configure nginx and supervisor
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| APP_NAME | Application name | DanaVision |
| APP_KEY | Encryption key (required) | - |
| APP_URL | Application URL | http://localhost:8080 |
| APP_ENV | Environment (`local` or `production`) | local |
| APP_DEBUG | Debug mode | true |
| DB_CONNECTION | Database driver | sqlite |
| DB_DATABASE | Database path | `/var/www/html/data/database.sqlite` |
| TZ | Timezone | America/Chicago |
| SCHEDULE_TIMEZONE | Timezone for scheduled tasks | America/Chicago |
| ALLOW_DB_INIT | Allow creating new database in production (safety flag) | false |

### ALLOW_DB_INIT Safety Flag

In production mode (`APP_ENV=production`), the container includes safety checks to prevent accidental data loss:

- If a volume marker exists but database is missing, the container refuses to start
- If the database file is empty (0 bytes), the container refuses to start

Set `ALLOW_DB_INIT=true` only when:
1. First-time setup of a new production instance
2. Intentionally creating a fresh database

## Volumes

### danavision_data

Stores the SQLite database file. Mount to persist data across container restarts.

**Both Development and Production:**
```yaml
volumes:
  - danavision_data:/var/www/html/data
```

> **Note:** We use `/var/www/html/data` (not `/database`) to avoid overwriting Laravel's `database/migrations` folder when the volume mounts.

### danavision_storage

Stores uploaded files and application storage.

```yaml
volumes:
  - danavision_storage:/var/www/html/storage/app
```

### Development Mounts

For development, mount source code directories:

```yaml
volumes:
  - ./backend/app:/var/www/html/app
  - ./backend/config:/var/www/html/config
  - ./backend/routes:/var/www/html/routes
  - ./backend/resources:/var/www/html/resources
```

## Common Commands

### Container Management

```bash
# Start container
docker compose up -d

# Stop container
docker compose down

# Restart container
docker restart danavision

# View logs
docker logs danavision
docker logs -f danavision  # Follow logs

# Shell access
docker exec -it danavision sh
```

### Build Frontend Assets

After making frontend changes:

```bash
docker exec danavision npm run build
```

### Clear Laravel Caches

```bash
docker exec danavision php artisan cache:clear
docker exec danavision php artisan route:clear
docker exec danavision php artisan config:clear
docker exec danavision php artisan view:clear
```

### Run Migrations

```bash
docker exec danavision php artisan migrate
```

### Database Operations

```bash
# Fresh migration (drops all tables)
docker exec danavision php artisan migrate:fresh

# Seed database
docker exec danavision php artisan db:seed
```

## Nginx Configuration

The `docker/nginx.conf` configures Nginx to:

- Serve static files directly
- Pass PHP requests to PHP-FPM
- Handle Inertia.js routing (all routes go to index.php)

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Supervisor Configuration

Supervisor manages both Nginx and PHP-FPM processes:

```ini
[supervisord]
nodaemon=true

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true

[program:php-fpm]
command=php-fpm --nodaemonize
autostart=true
autorestart=true
```

## Health Check

The container includes a health check that verifies the application is running:

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 10s
  retries: 3
```

The `/health` endpoint returns plain text:

```
healthy
```

## Troubleshooting

### Container Won't Start

1. Check logs: `docker logs danavision`
2. Verify APP_KEY is set
3. Check port 8080 is available

### Database Errors

1. Ensure database volume is mounted
2. Run migrations: `docker exec danavision php artisan migrate`
3. Check permissions on database file

### Frontend Not Loading

1. Rebuild assets: `docker exec danavision npm run build`
2. Clear view cache: `docker exec danavision php artisan view:clear`
3. Check browser console for errors

### 500 Errors

1. Check logs: `docker logs danavision`
2. Enable debug mode: `APP_DEBUG=true`
3. Check storage permissions

### Permission Issues

```bash
docker exec danavision chown -R www-data:www-data /var/www/html/storage
docker exec danavision chmod -R 775 /var/www/html/storage
```

## Production Deployment

For production:

1. Set `APP_ENV=production`
2. Set `APP_DEBUG=false`
3. Use proper APP_KEY
4. Configure reverse proxy/SSL
5. Mount volumes to persistent storage
6. Consider using managed database instead of SQLite

### Behind Reverse Proxy

If behind a reverse proxy (nginx, traefik), ensure:

1. Set `APP_URL` to your domain
2. Configure trusted proxies in Laravel
3. Forward proper headers (X-Forwarded-*)

## Backup & Restore

### Using the Backup Script

The recommended way to backup is using the included script:

```bash
./scripts/db-backup.sh backup    # Create a backup
./scripts/db-backup.sh restore   # Restore from latest backup
./scripts/db-backup.sh list      # List available backups
```

The script automatically detects the correct database path from the container environment.

### Manual Backup

```bash
docker cp danavision:/var/www/html/data/database.sqlite ./backup.sqlite
```

### Manual Restore

```bash
docker cp ./backup.sqlite danavision:/var/www/html/data/database.sqlite
docker exec danavision chown www-data:www-data /var/www/html/data/database.sqlite
```

### Full Backup (with storage)

```bash
# Stop container
docker compose down

# Backup volumes
docker run --rm \
  -v danavision_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/danavision-data.tar.gz -C /data .

docker run --rm \
  -v danavision_storage:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/danavision-storage.tar.gz -C /data .

# Restart container
docker compose up -d
```
