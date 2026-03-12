# Critical Fixes Roadmap

Security gaps and missing functionality that must be addressed for production readiness.

**Status**: COMPLETED  
**Completed**: 2026-01-27

---

## Task Checklist

- [x] Add admin middleware to settings and LLM routes in backend/routes/api.php
- [x] Implement POST/PUT/DELETE provider routes in LLMController.php
- [x] Create dedicated notification settings endpoint returning channel configuration

---

## 1. Backend Admin Authorization (Security) - COMPLETED

**Implementation**:
- Gate `manage-settings` defined in `backend/app/Providers/AppServiceProvider.php` (lines 53-55)
- Settings routes use `middleware('can:manage-settings')` in `backend/routes/api.php` (line 107)
- LLM routes use `middleware('can:manage-settings')` in `backend/routes/api.php` (line 139)

---

## 2. Notification Settings Backend - COMPLETED

**Implementation**:
- Created `backend/app/Http/Controllers/Api/NotificationSettingsController.php`
- Returns proper channel configuration format with `{ channels: [...] }`
- Routes at `GET/PUT /settings/notifications` in `backend/routes/api.php` (lines 110-111)

---

## 3. LLM Provider CRUD Routes - COMPLETED

**Implementation**:
- Routes added in `backend/routes/api.php` (lines 141-143):
  - `POST /llm/providers` → `storeProvider`
  - `PUT /llm/providers/{provider}` → `updateProvider`
  - `DELETE /llm/providers/{provider}` → `destroyProvider`
- Methods implemented in `backend/app/Http/Controllers/Api/LLMController.php` (lines 116-215)

---

## Files Reference

**Backend**:
- `backend/routes/api.php` - API routes with admin middleware
- `backend/app/Providers/AppServiceProvider.php` - Gate definitions
- `backend/app/Http/Controllers/Api/NotificationSettingsController.php` - Notification settings
- `backend/app/Http/Controllers/Api/LLMController.php` - LLM provider CRUD

**Documentation**:
- `docs/adr/012-admin-only-settings.md` - Admin-only settings decision
