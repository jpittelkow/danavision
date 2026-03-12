# Docker Container Audit Roadmap

**Priority:** MEDIUM  
**Dependencies:** None  
**Goal:** Ensure Docker configuration is correct, efficient, secure, and well-documented through comprehensive audit and fresh build testing.

## Overview

Audit all Docker-related configuration to verify:
- Fresh builds work correctly end-to-end
- Scripts are properly scripted and efficient
- Multi-stage build is optimized
- Development and production parity
- Security best practices followed

## Phase 1: Fresh Build Verification

Verify the container builds and runs correctly from scratch.

- [ ] Clean build test (no cache): `docker-compose build --no-cache`
- [ ] Verify all services start correctly (Nginx, PHP-FPM, Next.js, Meilisearch)
- [ ] Test health check endpoint responds
- [ ] Verify database migrations run on first start
- [ ] Test frontend loads and functions correctly
- [ ] Test backend API endpoints function correctly
- [ ] Verify log output is correct and useful
- [ ] Test on both Windows (Docker Desktop) and Linux hosts if possible

## Phase 2: Dockerfile Audit

Review `docker/Dockerfile` for correctness and efficiency.

### Build Optimization
- [ ] Review multi-stage build structure (frontend-builder → production)
- [ ] Audit layer ordering for cache efficiency (dependencies before source)
- [ ] Check for unnecessary files being copied into image
- [ ] Review `apk add` packages - remove any unused
- [ ] Verify PHP extensions installed are all needed
- [ ] Check Meilisearch installation method (curl | sh security concern?)
- [ ] Review Composer install flags (`--no-dev --optimize-autoloader`)

### Image Size
- [ ] Analyze final image size
- [ ] Identify opportunities to reduce size (multi-stage cleanup, Alpine optimization)
- [ ] Consider using `.dockerignore` effectively
- [ ] Verify no development dependencies in production image

### Security
- [ ] Verify no secrets baked into image
- [ ] Check file permissions are appropriate
- [ ] Review HEALTHCHECK configuration
- [ ] Consider running as non-root user where possible

## Phase 3: Script Audit

Review shell scripts for correctness and robustness.

### entrypoint.sh
- [ ] Verify all initialization steps are correct
- [ ] Check error handling (what happens if a step fails?)
- [ ] Verify idempotency (safe to run multiple times)
- [ ] Check database initialization logic
- [ ] Verify permission fixes are necessary and correct
- [ ] Review migration backup/restore logic
- [ ] Test development vs production mode detection
- [ ] Verify environment variable handling

### start-nextjs.sh
- [ ] Review Next.js startup logic
- [ ] Verify development vs production mode handling
- [ ] Check for race conditions with other services
- [ ] Verify proper signal handling for graceful shutdown

## Phase 4: Configuration Files Audit

Review Nginx, Supervisor, and PHP configurations.

### nginx.conf
- [ ] Verify proxy configuration for Next.js is correct
- [ ] Check PHP-FPM upstream configuration
- [ ] Review location blocks and routing logic
- [ ] Verify security headers are set
- [ ] Check gzip/compression settings
- [ ] Review timeout settings

### supervisord.conf
- [ ] Verify all services are configured correctly
- [ ] Check process priorities and dependencies
- [ ] Review restart policies
- [ ] Verify log configuration
- [ ] Check Meilisearch supervisor entry

### php.ini / php-dev.ini
- [ ] Compare production vs development settings
- [ ] Verify OPcache settings are appropriate
- [ ] Check memory limits
- [ ] Review upload size limits
- [ ] Verify error reporting settings

## Phase 5: Docker Compose Audit

Review docker-compose.yml and docker-compose.prod.yml.

### Development (docker-compose.yml)
- [ ] Verify volume mounts are correct
- [ ] Check named volumes configuration
- [ ] Review environment variable configuration
- [ ] Verify port mappings
- [ ] Check healthcheck configuration
- [ ] Review development-specific overrides

### Production (docker-compose.prod.yml)
- [ ] Compare with development config
- [ ] Verify production-appropriate settings
- [ ] Check resource limits (if any)
- [ ] Review restart policies
- [ ] Verify volume persistence strategy

## Phase 6: Documentation Update

Ensure Docker documentation is accurate and complete.

- [ ] Update `docs/docker.md` with any findings
- [ ] Update `.cursor/rules/local-docker-development.mdc` if needed
- [ ] Review `README.md` Docker quick start section
- [ ] Document any known issues or gotchas
- [ ] Add troubleshooting for common problems found

## Phase 7: Efficiency Improvements (Optional)

Implement improvements identified during audit.

- [ ] Optimize Dockerfile layer caching if issues found
- [ ] Reduce image size if opportunities identified
- [ ] Improve script error handling
- [ ] Add better logging where needed
- [ ] Implement any security improvements

## Testing Checklist

After completing audit, verify:

- [ ] `docker-compose up -d` from clean state works
- [ ] `docker-compose up -d --build` works
- [ ] `docker-compose build --no-cache && docker-compose up -d` works
- [ ] Application is fully functional after fresh build
- [ ] Hot reload works in development
- [ ] Meilisearch indexing works
- [ ] Database persistence works across restarts
- [ ] Logs are accessible and useful

## Files to Audit

| File | Purpose |
|------|---------|
| `docker/Dockerfile` | Multi-stage build definition |
| `docker/entrypoint.sh` | Container initialization |
| `docker/start-nextjs.sh` | Next.js startup script |
| `docker/nginx.conf` | Nginx configuration |
| `docker/supervisord.conf` | Process manager configuration |
| `docker/php.ini` | PHP production configuration |
| `docker/php-dev.ini` | PHP development configuration |
| `docker-compose.yml` | Development compose config |
| `docker-compose.prod.yml` | Production compose config |
| `.dockerignore` | Build context exclusions |

## Success Criteria

- Fresh builds complete without errors
- All services start and pass health checks
- Application is fully functional
- No unnecessary packages or files in image
- Scripts handle errors gracefully
- Documentation is accurate and complete

## Implementation Journal

- [Docker Container Audit](../journal/2026-02-05-docker-container-audit.md)
- [Docker Build Optimization & Security Updates](../journal/2026-02-05-docker-optimization-and-security-updates.md)
