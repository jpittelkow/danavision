# ADR 001: Technology Stack

## Status
Accepted

## Date
2025-01-01

## Context
DanaVision needs a robust technology stack that supports:
- Mobile-first development for iOS and Android
- RESTful API backend with authentication
- Price tracking and AI integration capabilities
- Single-container Docker deployment

## Decision
We will use the following technology stack:

### Backend
- **Laravel 11** - Modern PHP framework with excellent API support
- **PHP 8.2+** - Latest PHP with performance improvements
- **SQLite** - Embedded database for simplicity and portability
- **Laravel Sanctum** - Token-based authentication for mobile apps

### Frontend
- **React Native 0.76+** - Cross-platform mobile development
- **Expo 52+** - Managed workflow for faster development
- **TypeScript** - Type safety and better developer experience
- **NativeWind** - Tailwind CSS for consistent styling

### Infrastructure
- **Docker** - Single container for both dev and production
- **Nginx + PHP-FPM** - High-performance web serving
- **Supervisor** - Process management in container

## Consequences

### Positive
- Single codebase for iOS and Android
- Laravel provides mature ecosystem for APIs
- SQLite eliminates database server management
- Docker ensures consistent environments
- TypeScript catches errors at compile time

### Negative
- React Native has native module limitations
- SQLite has concurrent write limitations
- PHP may not be as performant as Go/Rust for high load

## Related Decisions
- [ADR-004: Mobile-First Architecture](004-mobile-first-architecture.md)
