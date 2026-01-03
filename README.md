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
