# Security SAST Automation - 2026-02-02

## Overview

Implemented Phase 2 of the security compliance roadmap: automated Static Application Security Testing (SAST) integrated into the CI/CD pipeline and local development workflow.

## Implementation Approach

### Tool Selection

Selected three complementary tools based on the recommendations in the security compliance roadmap:

| Tool | Target | Purpose |
|------|--------|---------|
| PHPStan + Larastan | Backend (PHP) | Static analysis with strict rules and Laravel-specific checks |
| eslint-plugin-security | Frontend (JS/TS) | Security-focused linting rules for JavaScript/TypeScript |
| Semgrep | Full codebase | OWASP patterns, framework-specific rules, comprehensive security audit |

### Backend: PHPStan Configuration

Added to `backend/composer.json`:
- `phpstan/phpstan` - Core static analyzer
- `phpstan/phpstan-strict-rules` - Strict type and safety rules
- `larastan/larastan` - Laravel-specific analysis

Created `backend/phpstan.neon` with:
- Level 6 analysis (strict but practical)
- Laravel-aware rules via Larastan
- Ignored safe Laravel patterns (dynamic Builder methods)
- Excluded vendor, storage, and cache directories

### Frontend: ESLint Security Plugin

Added `eslint-plugin-security` to `frontend/package.json` and created `frontend/.eslintrc.json`:
- Extended Next.js core-web-vitals config
- Added security plugin with recommended rules
- Configured rule severities (error for critical, warn for informational)

Key rules enabled:
- `detect-eval-with-expression` (error) - Prevents code injection
- `detect-unsafe-regex` (error) - Prevents ReDoS
- `detect-bidi-characters` (error) - Prevents trojan source attacks
- `detect-object-injection` (warn) - Flags dynamic property access
- `detect-possible-timing-attacks` (warn) - Flags comparison timing leaks

### CI/CD Integration

Updated `.github/workflows/ci.yml`:
- Added PHPStan step to backend-tests job
- Created new `security-scan` job with Semgrep
- Docker build now depends on all three jobs passing

Semgrep rule sets:
- `p/security-audit` - General security patterns
- `p/php-laravel` - Laravel-specific vulnerabilities
- `p/typescript` - TypeScript/JavaScript security
- `p/owasp-top-ten` - OWASP Top 10 coverage

### Local Development Script

Created `scripts/security-scan.sh`:
- Runs all three tools sequentially
- Color-coded output for pass/fail
- `--quick` flag to skip Semgrep for faster iteration
- `--fix` flag to auto-fix ESLint issues
- Graceful handling of missing tools with install instructions

## Key Files Created/Modified

### New Files
- `backend/phpstan.neon` - PHPStan configuration
- `frontend/.eslintrc.json` - ESLint security configuration
- `scripts/security-scan.sh` - Local security scan script

### Modified Files
- `backend/composer.json` - Added PHPStan dependencies and script
- `frontend/package.json` - Added eslint-plugin-security
- `.github/workflows/ci.yml` - Added security scanning jobs
- `docs/plans/security-compliance-roadmap.md` - Marked SAST complete
- `docs/roadmaps.md` - Updated status
- `docs/development.md` - Added security scanning section

## Observations

- PHPStan at level 6 provides good coverage without excessive false positives
- Larastan handles Laravel's dynamic patterns (facades, query builders) well
- ESLint security plugin integrates seamlessly with Next.js config
- Semgrep's free tier is sufficient for open source projects
- The local script enables developers to catch issues before CI

## Trade-offs

- PHPStan level 6 vs higher: Level 6 catches most issues; higher levels require more type annotations
- Semgrep in CI only: Running locally is optional to avoid slowing down development iteration
- Warning vs error: Some rules (like object-injection) warn instead of error to avoid blocking legitimate dynamic access patterns

## Testing Notes

To verify the implementation:

1. **PHPStan**: Run `cd backend && composer phpstan` - should complete without errors
2. **ESLint**: Run `cd frontend && npm run lint` - should include security checks
3. **CI**: Push to a branch and verify all three jobs pass
4. **Local script**: Run `./scripts/security-scan.sh --quick` - should show pass/fail status

## Next Steps

- Monitor CI for false positives and adjust rules as needed
- Consider adding Psalm for additional PHP security analysis
- Document secure development lifecycle (SDLC) for compliance
