# Changelog Docker Fix & Branding Null Serialization Bug

**Date**: 2026-02-15  
**Type**: Bug Fix

## Summary

Fixed two issues:
1. **Changelog not working in Docker** — `CHANGELOG.md` was not available inside the container (dev or prod)
2. **Broken image after deleting branding logos** — `SystemSetting` model returned the string `"null"` instead of PHP `null`, causing the Logo component to render a broken `<img src="null">`

## Issue 1: Changelog Not Available in Docker

The `ChangelogService` reads from `base_path('../CHANGELOG.md')` which resolves to `/var/www/html/CHANGELOG.md`. However, the file was never copied into the Docker image or volume-mounted for development.

### Fix
- **Dockerfile**: Added `COPY --chown=www-data:www-data CHANGELOG.md ./CHANGELOG.md` alongside the existing `VERSION` copy
- **docker-compose.yml**: Added `./CHANGELOG.md:/var/www/html/CHANGELOG.md:ro` volume mount for development

## Issue 2: SystemSetting Null Serialization Bug

### Root Cause

The `SystemSetting` model's value accessor had a subtle bug with the null-coalescing operator (`??`):

```php
// BEFORE (buggy):
return is_string($value) ? json_decode($value, true) ?? $value : $value;
```

When `null` was stored as a setting value:
1. **Setter**: `json_encode(null)` stored the 4-character string `"null"` in the database
2. **Getter**: `json_decode("null", true)` correctly returned PHP `null`
3. **Bug**: `null ?? $value` — the `??` operator treated `null` as "missing" and returned the raw string `"null"` instead

This caused the branding API to return `{ "logo_url": "null" }` (string) instead of `{ "logo_url": null }` (JSON null). The frontend `Logo` component then tried to render `<img src="null">`, showing a broken image.

### Fix

**Backend (root cause)**: Extracted a `jsonDecodeValue()` helper method that uses `json_last_error() === JSON_ERROR_NONE` to distinguish between a valid JSON decode (including the literal `null`) and invalid JSON:

```php
// AFTER (correct):
$decoded = json_decode($value, true);
if (json_last_error() === JSON_ERROR_NONE) {
    return $decoded;  // Returns PHP null for "null", [] for "[]", etc.
}
return $value;  // Returns raw string for non-JSON values
```

**Frontend (defense-in-depth)**: Added sanitization in `app-config.tsx` and the branding settings page to treat the string `"null"` as actual null. This protects against any existing database records that still contain the bad serialized value.

### Impact

This bug affected **any** `SystemSetting` value set to `null`, not just branding. The fix is systemic and corrects the behavior for all settings.

## Files Modified

- `backend/app/Models/SystemSetting.php` — Fixed `jsonDecodeValue()` to handle null correctly
- `frontend/lib/app-config.tsx` — Added `sanitize()` helper for defense-in-depth
- `frontend/app/(dashboard)/configuration/branding/page.tsx` — Added `clean()` helper in `fetchSettings`
- `docker/Dockerfile` — Added `COPY CHANGELOG.md`
- `docker-compose.yml` — Added CHANGELOG.md volume mount

## Anti-Pattern Identified

**Null-coalescing on json_decode**: Never use `json_decode($v) ?? $fallback` when `null` is a valid decoded value. Use `json_last_error() === JSON_ERROR_NONE` to check decode success instead.
