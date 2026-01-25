# DanaVision Documentation

## Overview

DanaVision is a smart shopping list and price tracking application that helps users find the best deals. The application uses AI-powered product identification and multi-vendor price comparison to help users save money on their purchases.

## Architecture

The application uses a modern monolithic architecture with Laravel Inertia.js:

- **Backend**: Laravel 11 (PHP 8.2+) providing controllers and business logic
- **Frontend**: React 18 (TypeScript) via Inertia.js for SPA-like experience
- **Build Tool**: Vite for fast development and optimized production builds
- **Infrastructure**: Docker-based deployment with single container
- **Authentication**: Laravel Sanctum for session-based authentication
- **Database**: SQLite (embedded, zero-config)

## Technology Stack

### Backend
- Laravel 11.x
- PHP 8.2+
- Laravel Sanctum
- SQLite database
- Pest PHP (testing)

### Frontend
- React 18.x
- TypeScript 5.x
- Inertia.js 2.x
- Vite 6.x
- Tailwind CSS 3.x
- Radix UI components
- Lucide React icons
- Playwright (E2E testing)

### Infrastructure
- Docker (single container deployment)
- Nginx (web server)
- PHP-FPM (PHP processor)
- Python 3.11 + Crawl4AI (web scraping)
- Chromium (headless browser for scraping)
- Supervisor (process management)

## Project Structure

```
danavision/
├── backend/                    # Laravel application
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    # Request handlers
│   │   │   └── Middleware/     # Request middleware
│   │   ├── Models/             # Eloquent models
│   │   ├── Policies/           # Authorization policies
│   │   └── Services/           # Business logic
│   │       ├── AI/             # AI providers and agents
│   │       ├── Crawler/        # Web scraping (Crawl4AI integration)
│   │       ├── Mail/           # Email services
│   │       └── PriceApi/       # Price lookup services
│   ├── database/
│   │   ├── factories/          # Model factories
│   │   ├── migrations/         # Database migrations
│   │   └── seeders/            # Database seeders
│   ├── resources/
│   │   ├── css/                # Stylesheets
│   │   ├── js/                 # React frontend
│   │   │   ├── Components/     # Reusable components
│   │   │   ├── Layouts/        # Page layouts
│   │   │   ├── Pages/          # Page components
│   │   │   ├── hooks/          # Custom React hooks
│   │   │   ├── lib/            # Utilities
│   │   │   └── types/          # TypeScript definitions
│   │   └── views/              # Blade templates
│   ├── routes/
│   │   ├── web.php             # Web routes
│   │   └── api.php             # API routes
│   ├── tests/                  # Pest PHP tests
│   └── e2e/                    # Playwright E2E tests
├── docker/                     # Docker configuration
├── docs/                       # Documentation
│   └── adr/                    # Architecture Decision Records
└── docker-compose.yml          # Container orchestration
```

## Quick Start

### Docker Deployment (Recommended)

```bash
# Generate APP_KEY
docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Edit docker-compose.yml and set your APP_KEY

# Start DanaVision
docker compose up -d

# Build frontend assets (after code changes)
docker exec danavision npm run build
```

Access the application at: http://localhost:8080

### Local Development

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Frontend (separate terminal)
cd backend
npm install
npm run dev
```

## Key Features

- **Smart Add**: AI-powered product identification from images
- **Price Tracking**: Monitor prices across multiple vendors
- **Shopping Lists**: Organize items into lists
- **Price Alerts**: Get notified when prices drop
- **Multi-Provider AI**: Support for Claude, OpenAI, Gemini, and local Ollama
- **Crawl4AI Integration**: Self-hosted web scraping for price discovery (no external API costs)
- **Price APIs**: Optional integration with SerpAPI and Rainforest API
- **Mobile-First**: Responsive design with camera support

## Documentation Index

| Document | Description |
|----------|-------------|
| [CONTRIBUTING.md](CONTRIBUTING.md) | Development guidelines and requirements |
| [REQUIREMENTS.md](REQUIREMENTS.md) | Quick reference checklist |
| [DOCUMENTATION_LARAVEL.md](DOCUMENTATION_LARAVEL.md) | Backend API documentation |
| [DOCUMENTATION_REACT.md](DOCUMENTATION_REACT.md) | Frontend/Inertia documentation |
| [DOCUMENTATION_DOCKER.md](DOCUMENTATION_DOCKER.md) | Docker deployment guide |
| [DOCUMENTATION_TESTING.md](DOCUMENTATION_TESTING.md) | Testing guide (Pest + Playwright) |
| [ADRs](adr/README.md) | Architecture Decision Records |

## Data Model Overview

The application uses a user-based data model where:
- Each user owns their shopping lists
- List items belong to shopping lists
- AI providers are configured per user
- Settings are stored per user

### Core Entities

| Entity | Description |
|--------|-------------|
| User | Application user with authentication |
| ShoppingList | Collection of items to track |
| ListItem | Product with price tracking |
| ItemVendorPrice | Price data from specific vendor |
| PriceHistory | Historical price records |
| AIProvider | User's AI provider configuration |
| Setting | User preferences |

## API Structure

The application uses Inertia.js, which means most routes return full page renders rather than JSON. API endpoints are available for specific operations.

### Web Routes (Inertia)

| Route | Description |
|-------|-------------|
| `GET /dashboard` | Main dashboard |
| `GET /smart-add` | Smart Add feature |
| `GET /lists` | Shopping lists index |
| `GET /lists/{id}` | List detail |
| `GET /search` | Product search |
| `GET /settings` | User settings |

### API Routes (JSON)

| Route | Description |
|-------|-------------|
| `POST /api/search` | Text search |
| `POST /api/search/image` | Image search |
| `GET /api/health` | Health check |

## Security

- User data isolation via `user_id` foreign keys
- Policy-based authorization for all resources
- CSRF protection via Laravel Sanctum
- Session-based authentication

## Testing

```bash
# Backend tests
cd backend && ./vendor/bin/pest

# E2E tests (requires running app)
cd backend && npm run test:e2e
```

See [DOCUMENTATION_TESTING.md](DOCUMENTATION_TESTING.md) for complete testing guide.

## Deployment

DanaVision is deployed as a single Docker container with everything included:
- Nginx web server
- PHP-FPM 8.3
- SQLite database (embedded)
- React frontend (pre-built)
- Python + Crawl4AI (web scraping)
- Chromium browser (headless)

**Recommended Resources**: 2GB RAM, 2 CPU cores

See [DOCUMENTATION_DOCKER.md](DOCUMENTATION_DOCKER.md) for detailed deployment information.
