# DanaVision 💜

Smart Shopping Price Tracker for Dana - Track prices, get alerts on drops, and find the best deals.

![DanaVision](backend/public/images/danavision_icon.png)

## Features

- 📊 **Dashboard** - Overview of price drops and potential savings
- 📝 **Shopping Lists** - Organize items into multiple lists
- 🔍 **Product Search** - Text and AI-powered image search
- 💰 **Price Tracking** - Monitor prices across retailers
- 🔔 **Price Alerts** - Get notified on price drops and all-time lows
- 🤖 **AI Integration** - Claude, OpenAI, Gemini support for product identification
- 👥 **List Sharing** - Share lists with family/friends

## Tech Stack

- **Backend**: Laravel 11 + Inertia.js
- **Frontend**: React + TypeScript + Tailwind CSS
- **Database**: SQLite
- **Container**: Docker (Nginx + PHP-FPM + Supervisor)
- **Testing**: Pest PHP (Feature & Unit tests)
- **AI**: Multi-provider (Claude, OpenAI, Gemini)
- **Price APIs**: SerpApi (Google Shopping), Rainforest (Amazon)

## Quick Start

### Prerequisites

- Docker & Docker Compose

### Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/jpittelkow/danavision.git
   cd danavision
   ```

2. Configure environment:
   ```bash
   cp .env.example .env
   # Generate APP_KEY:
   docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
   # Add the generated key to .env file
   ```

3. Start the application:
   ```bash
   docker compose up -d
   ```

4. Run migrations and seed:
   ```bash
   docker compose exec danavision php artisan migrate --force
   docker compose exec danavision php artisan db:seed --force
   ```

5. Open http://localhost:8080

### Using Pre-built Docker Image

Docker images are automatically built and pushed to GitHub Container Registry on every push to the main branch. You can use the pre-built image instead of building locally:

```bash
# Pull the latest image
docker pull ghcr.io/jpittelkow/danavision:latest

# Or use docker-compose with the pre-built image (update docker-compose.yml to use image instead of build)
```

View the package: https://github.com/jpittelkow/danavision/pkgs/container/danavision

## Docker Deployment

### Ports

| Port | Description |
|------|-------------|
| `80` (container) → `8080` (host) | HTTP web server (Nginx) |

You can change the host port by modifying `docker-compose.yml`:
```yaml
ports:
  - "3000:80"  # Access via http://localhost:3000
```

### Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_KEY` | **Yes** | - | Laravel encryption key (generate with command below) |
| `APP_NAME` | No | `DanaVision` | Application name shown in UI |
| `APP_URL` | No | `http://localhost:8080` | Full URL where app is accessed |
| `APP_ENV` | No | `local` | Environment: `local`, `production` |
| `APP_DEBUG` | No | `true` | Enable debug mode (set `false` in production) |
| `DB_CONNECTION` | No | `sqlite` | Database driver |
| `DB_DATABASE` | No | Dev: `/var/www/html/data/database.sqlite` | Database path (see note below) |
| `TZ` | No | `America/Chicago` | Timezone for app and scheduler |
| `SCHEDULE_TIMEZONE` | No | `America/Chicago` | Timezone for scheduled tasks |
| `ALLOW_DB_INIT` | No | `false` | Safety flag: set `true` for first-time prod setup |

> **Database Path Note:** Development uses `/var/www/html/data/`, production uses `/var/www/html/database/`. The entrypoint script reads `DB_DATABASE` to determine the correct path automatically.

**Generate APP_KEY:**
```bash
docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

### Volumes

| Volume | Container Path | Description |
|--------|----------------|-------------|
| Data | `/var/www/html/data` | SQLite database directory (persistent) |
| Storage | `/var/www/html/storage/app` | Uploaded files (persistent) |
| Logs | `/var/www/html/storage/logs` | Laravel logs (optional, for debugging) |

> ℹ️ **Note:** The database is stored in `/var/www/html/data/` (separate from `/var/www/html/database/` which contains migrations). This allows you to safely mount the entire data directory without affecting migrations.

#### For Unraid / Portainer (bind mounts)

**Volume mappings:**
```
Host Path                                → Container Path
/mnt/user/appdata/danavision/data        → /var/www/html/data
/mnt/user/appdata/danavision/storage     → /var/www/html/storage/app
/mnt/user/appdata/danavision/logs        → /var/www/html/storage/logs
```

#### For docker-compose (named volumes)

```yaml
volumes:
  - danavision_data:/var/www/html/data
  - danavision_storage:/var/www/html/storage/app
```

### Example docker-compose.yml for Production

> **Note:** Production uses `/var/www/html/database/` for the database path, while development uses `/var/www/html/data/`. Use `docker-compose.prod.yml` from the repo for production deployments.

```yaml
services:
  danavision:
    image: ghcr.io/jpittelkow/danavision:latest
    container_name: danavision
    ports:
      - "8080:80"
    volumes:
      # Production uses /database path
      - danavision_data:/var/www/html/database
      - danavision_storage:/var/www/html/storage/app
    environment:
      - APP_KEY=base64:YOUR_GENERATED_KEY_HERE
      - APP_URL=https://your-domain.com
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_DATABASE=/var/www/html/database/database.sqlite
      - TZ=America/Chicago
      - SCHEDULE_TIMEZONE=America/Chicago
      # Set to true only for first-time setup
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

**First-time production setup:**
```bash
ALLOW_DB_INIT=true docker compose up -d
```

### Deployment Steps

1. **Create your deployment directory:**
   ```bash
   mkdir danavision && cd danavision
   ```

2. **Create docker-compose.yml** (use example above)

3. **Generate and set APP_KEY:**
   ```bash
   docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
   # Copy the output and replace YOUR_GENERATED_KEY_HERE in docker-compose.yml
   ```

4. **Start the container:**
   ```bash
   docker compose up -d
   ```

5. **Seed the database (first time only):**
   ```bash
   docker compose exec danavision php artisan db:seed --force
   ```

6. **Access the application** at `http://your-server:8080`

### Container Features

The container automatically handles on startup:
- ✅ Creates SQLite database if missing
- ✅ Sets correct file permissions
- ✅ Runs database migrations
- ✅ Caches configuration in production mode

### Health Check

The container includes a health check endpoint at `/health`. Docker will monitor this and restart the container if it becomes unhealthy.

### Logs

```bash
# View all container logs
docker compose logs -f danavision

# View Laravel logs specifically
docker compose exec danavision tail -f /var/www/html/storage/logs/laravel.log
```

### Updating

```bash
# Pull latest image
docker compose pull

# Recreate container with new image
docker compose up -d
```

## Test Credentials

After seeding the database, the following test users are available:

| User | Email | Password |
|------|-------|----------|
| Dana (Primary) | `dana@danavision.app` | `password` |
| Test User | `test@example.com` | `password` |

> **Note**: The database must be seeded for these credentials to work. Run `php artisan db:seed` if login fails.

## Development

### Frontend Development

The frontend is built with Vite. To rebuild after changes:

```bash
docker compose exec danavision npm run build
```

For development with hot reload:

```bash
docker compose exec danavision npm run dev
```

### Backend Development

PHP files are mounted from `./backend/` for live reload. The following directories are mapped:

- `backend/app/` - Application code
- `backend/config/` - Configuration files
- `backend/routes/` - Route definitions
- `backend/resources/` - Views and frontend assets
- `backend/database/` - Migrations, seeders, factories
- `backend/tests/` - Test files

## Testing

DanaVision uses [Pest PHP](https://pestphp.com/) for testing, built on top of PHPUnit.

### Running Tests

```bash
# Run all tests
docker compose exec danavision ./vendor/bin/pest

# Run specific test file
docker compose exec danavision ./vendor/bin/pest --filter="FullAuthFlow"

# Run with verbose output
docker compose exec danavision ./vendor/bin/pest -v

# Run tests with coverage (requires Xdebug)
docker compose exec danavision ./vendor/bin/pest --coverage
```

### Test Suite Overview

| Suite | Tests | Description |
|-------|-------|-------------|
| **Auth Tests** | 11 | Login, logout, registration, full auth flow |
| **Policy Tests** | 5 | List ownership and sharing permissions |
| **Shopping List Tests** | 5 | CRUD operations for lists |
| **List Items Tests** | 4 | Adding, purchasing, and deleting items |

**Total: 32 tests, 80 assertions**

### Test Categories

#### Authentication Tests (`tests/Feature/Auth/`)
- `LoginTest.php` - Login page rendering and authentication
- `LogoutTest.php` - Logout functionality and auth middleware
- `RegistrationTest.php` - User registration and validation
- `FullAuthFlowTest.php` - Complete signup → logout → login flow

#### Policy Tests (`tests/Feature/Policies/`)
- `ListOwnershipTest.php` - Owner/non-owner access controls

#### Shopping List Tests (`tests/Feature/ShoppingLists/`)
- `CreateListTest.php` - List creation and viewing
- `ListItemsTest.php` - Item management (add, purchase, delete)

### Writing New Tests

Tests are in `backend/tests/Feature/`. Use Pest's expressive syntax:

```php
<?php

use App\Models\User;

test('users can perform action', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->post('/action', [
        'data' => 'value',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('table', ['column' => 'value']);
});
```

## CI/CD

This repository uses GitHub Actions to automatically build and push Docker images to GitHub Container Registry (ghcr.io) on every push to the main branch. The workflow:

- Builds the Docker image using Docker Buildx
- Tags images as `ghcr.io/jpittelkow/danavision:latest` and `ghcr.io/jpittelkow/danavision:main-{sha}`
- Pushes to GitHub Container Registry
- Uses GitHub Actions cache for faster builds

View the workflow: [.github/workflows/docker-build.yml](.github/workflows/docker-build.yml)

## Architecture

```
DanaVision/
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/   # Inertia controllers
│   │   ├── Models/             # Eloquent models
│   │   ├── Policies/           # Authorization policies
│   │   └── Services/           # AI, Price API, Mail services
│   ├── resources/
│   │   ├── js/
│   │   │   ├── Pages/          # React pages
│   │   │   ├── Layouts/        # Shared layouts
│   │   │   └── types/          # TypeScript definitions
│   │   └── css/
│   ├── routes/
│   └── tests/
│       └── Feature/            # Feature tests
│           ├── Auth/
│           ├── Policies/
│           └── ShoppingLists/
├── docker/
│   ├── Dockerfile
│   ├── entrypoint.sh        # Container startup script
│   ├── nginx.conf
│   └── supervisord.conf
├── docker-compose.yml
└── README.md
```

## API Configuration

Configure your AI and price tracking APIs in Settings:

- **AI Provider**: Claude (Anthropic), OpenAI, Google Gemini, or Local
- **Price Provider**: SerpApi (Google Shopping) or Rainforest (Amazon)

## UI Features

### Password Visibility Toggle

Both login and registration forms include a password visibility toggle button, allowing users to see their password as they type.

### Form Validation

- Real-time validation feedback
- Clear error messages
- Proper `autocomplete` attributes for browser autofill

## Brand Colors

| Color | Hex | Usage |
|-------|-----|-------|
| Purple (Primary) | `#6B4EAB` | Buttons, headers, active elements |
| Purple Light | `#9B7FC8` | Inactive elements, subtle accents |
| Purple Dark | `#3D2E5C` | Text, emphasis |
| Gold | `#F5A623` | Secondary buttons, highlights |
| Coral | `#E57373` | Alerts, warnings |
| Cream | `#FAF5F0` | Background |

## Troubleshooting

### Login doesn't work / Invalid credentials

Make sure the database is seeded:
```bash
docker compose exec danavision php artisan migrate:fresh --seed
```

### Tests fail with 500 errors

Ensure you have an `.env` file in the container:
```bash
docker compose exec danavision php artisan key:generate
```

### Frontend changes not showing

Rebuild the assets:
```bash
docker compose exec danavision npm run build
```

## License

MIT
