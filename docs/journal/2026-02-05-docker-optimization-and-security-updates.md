# Docker Build Optimization & Security Updates - 2026-02-05

## Overview

Major upgrade session to optimize Docker builds, fix all npm vulnerabilities, and upgrade to Next.js 16 with ESLint 9 flat config.

## Changes Made

### 1. Docker Build Optimizations

**Meilisearch Update**
- Updated from v1.6.2 to v1.34.2 (latest)
- Brings AI-powered search, vector search, and security patches
- Required clearing Meilisearch data volume due to version incompatibility

**Composer Version Pinning**
- Changed from `composer:latest` to `composer:2.8`
- Ensures reproducible builds

**Next.js Standalone Output**
- Refactored Dockerfile to copy `.next/standalone` instead of full `node_modules`
- Reduces image size by ~150-200MB
- Uses `COPY --chown` flags to avoid slow recursive chown operations

**start-nextjs.sh Updates**
- Production mode now uses `node server.js` for standalone builds
- Development mode installs dependencies and runs `npm run dev`
- Fixed npm cache permissions (`/tmp/.npm`)
- Fixed node_modules detection check

**apt-get Optimization**
- Kept `npm` for development mode compatibility
- Removed slow recursive `chown -R /var/www/html` operation

### 2. Security Vulnerability Fixes

Ran `npm audit fix --force` to address 8 vulnerabilities (4 moderate, 4 high):

| Package | Before | After | Notes |
|---------|--------|-------|-------|
| next | 14.2.35 | 16.1.6 | Major version upgrade |
| eslint-config-next | 14.2.35 | 16.1.6 | Major version upgrade |
| eslint | 8.57.1 | 9.39.2 | Major version upgrade |
| vitest | 1.6.1 | 4.0.18 | Major version upgrade |

**Vulnerabilities Fixed:**
- esbuild dev server security issue (moderate)
- glob CLI command injection (high)
- Next.js Image Optimizer DoS (high)
- Next.js RSC deserialization DoS (high)

### 3. Next.js 16 Migration

**next.config.js Changes**
- Removed webpack polling config (Turbopack handles file watching)
- Added `turbopack: {}` to enable Turbopack (default in Next.js 16)

```javascript
// Before
webpack: (config, { dev }) => {
  if (dev) {
    config.watchOptions = { poll: 1000, aggregateTimeout: 300 };
  }
  return config;
},

// After
turbopack: {},
```

### 4. ESLint 9 Flat Config Migration

**Deleted:** `.eslintrc.json`

**Created:** `eslint.config.mjs` with flat config format:

```javascript
import nextPlugin from "@next/eslint-plugin-next";
import reactPlugin from "eslint-plugin-react";
import hooksPlugin from "eslint-plugin-react-hooks";

export default [
  {
    plugins: {
      "@next/next": nextPlugin,
      react: reactPlugin,
      "react-hooks": hooksPlugin,
    },
    rules: {
      ...nextPlugin.configs.recommended.rules,
      ...nextPlugin.configs["core-web-vitals"].rules,
      "react/no-unescaped-entities": "off",
    },
  },
  { ignores: [".next/*", "node_modules/*"] },
];
```

**package.json Changes:**
- Updated lint script from `next lint` to `eslint .`
- Note: `next lint` command was removed in Next.js 16

### 5. Frontend Code Quality Fixes

**useEffect Dependencies (16 files)**
- Added `useCallback` wrappers for functions used in useEffect
- Fixed missing dependency warnings

**Image Components (10 instances)**
- Replaced `<img>` tags with `next/image` components
- Added `unoptimized` prop for external/dynamic URLs

**Files Modified:**
- `components/storage/file-preview.tsx`
- `components/logo.tsx`
- `components/branding-preview.tsx`
- `components/admin/user-table.tsx`
- `components/onboarding/steps/profile-step.tsx`
- `components/onboarding/steps/welcome-step.tsx`
- `app/(dashboard)/configuration/branding/page.tsx`
- `app/(dashboard)/user/security/page.tsx`
- Plus 14 files for useEffect dependency fixes

## Challenges Encountered

1. **npm ci vs npm install**: Cross-platform lock file issues (fsevents not included on Windows) required using `npm install` instead of `npm ci`

2. **ESLint flat config circular reference**: FlatCompat approach had issues; switched to direct plugin configuration

3. **Development mode npm permissions**: Fixed by setting `npm_config_cache=/tmp/.npm`

4. **Meilisearch data incompatibility**: Required clearing data volume when upgrading from 1.6.2 to 1.34.2

## Results

| Metric | Before | After |
|--------|--------|-------|
| npm vulnerabilities | 8 | 0 |
| Next.js version | 14.2.35 | 16.1.6 |
| ESLint version | 8.57.1 | 9.39.2 |
| Meilisearch version | 1.6.2 | 1.34.2 |
| Bundler | Webpack | Turbopack |
| ESLint config | Legacy (.eslintrc) | Flat config |

## Testing Notes

- Docker build succeeds
- All services start correctly (nginx, php-fpm, meilisearch, nextjs)
- 5 pre-existing test failures (not related to upgrades)
- ESLint passes with 0 warnings

## Next Steps (Future Considerations)

- Fix the 5 pre-existing test failures in `__tests__/`
- Consider upgrading other deprecated packages flagged during npm install
- Monitor for any Turbopack-specific issues in development
