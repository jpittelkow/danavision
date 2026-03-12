# Environment to Database Migration Roadmap

Move application configuration from environment variables to database-stored settings for UI-based management.

**Priority**: HIGH  
**Status**: Complete (2026-01-29)  
**Last Updated**: 2026-01-29

**Dependencies**:
- [Settings Restructure](settings-restructure-roadmap.md) - Configuration page structure and settings table

---

## Task Checklist

- [x] Create settings table and migration (existing; added `is_encrypted` column)
- [x] Create SettingService with env fallback
- [x] Migrate mail configuration to database (Phase 2 complete)
- [x] Migrate notification channel configuration to database (Phase 3 complete)
- [x] Migrate LLM system-wide settings to database (Phase 4 complete)
- [x] Migrate SSO provider configuration to database (Phase 5 complete)
- [x] Migrate backup configuration to database
- [x] Create admin settings UI for each category (mail, notification credentials, LLM system, SSO, backup)
- [x] Add settings caching layer (file cache in SettingService)
- [x] Document which settings remain env-only (ADR-015)

---

## 1. Overview

**Purpose**: Allow administrators to configure application settings through the UI rather than requiring environment file changes and container restarts.

**Benefits**:
- No restart required for configuration changes
- Non-technical users can manage settings
- Settings can be changed in production without redeployment
- Audit trail for configuration changes

**Current State**: All 300+ configuration values are read from `.env` via Laravel config files.

---

## 2. Settings That Should Move to Database

### 2.1 HIGH Priority (User-Facing Features)

| Category | Env Variables | Count |
|----------|---------------|-------|
| Mail | `MAIL_*` | 8 |
| Notifications | `TELEGRAM_*`, `DISCORD_*`, `SLACK_*`, `TWILIO_*`, `VONAGE_*`, `SIGNAL_*`, `VAPID_*`, `FCM_*` | 15 |
| LLM Providers | `ANTHROPIC_*`, `OPENAI_*`, `GEMINI_*`, `OLLAMA_*`, `AZURE_OPENAI_*` | 10 |
| SSO Providers | `GOOGLE_*`, `GITHUB_*`, `MICROSOFT_*`, `APPLE_*`, `DISCORD_*`, `GITLAB_*`, `OIDC_*` | 21 |
| Backup | `BACKUP_*`, `AWS_*` (for S3) | 8 |

### 2.2 MEDIUM Priority (System Settings)

| Category | Env Variables | Count |
|----------|---------------|-------|
| App Settings | `APP_NAME`, `APP_TIMEZONE`, `APP_URL` | 3 |
| Session | `SESSION_LIFETIME` | 1 |
| Queue | Queue driver settings | 3 |

### 2.3 Settings That MUST Stay in Env

These cannot be database-stored due to bootstrap/security requirements:

| Category | Reason |
|----------|--------|
| `APP_KEY` | Encryption key - needed before DB connection |
| `APP_ENV`, `APP_DEBUG` | Affects error handling before DB |
| `DB_*` | Database connection itself |
| `LOG_*` | Logging needed before DB connection |
| `CACHE_STORE` | Cache driver needed for settings cache |

---

## 3. Database Schema

### 3.1 Settings Table

```sql
CREATE TABLE settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    group VARCHAR(50) NOT NULL,        -- 'mail', 'notifications', 'llm', etc.
    key VARCHAR(100) NOT NULL,         -- 'smtp_host', 'openai_api_key', etc.
    value TEXT NULL,                   -- Stored value (encrypted for secrets)
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_setting (group, key)
);
```

### 3.2 Settings Audit Table (Optional)

```sql
CREATE TABLE settings_audit (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    setting_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_at TIMESTAMP,
    FOREIGN KEY (setting_id) REFERENCES settings(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 4. SettingService Implementation

### 4.1 Core Service

```php
class SettingService
{
    // Get setting with env fallback
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        // 1. Check cache
        // 2. Check database
        // 3. Fall back to env/config
        // 4. Return default
    }
    
    // Set setting in database
    public function set(string $group, string $key, mixed $value, bool $encrypt = false): void
    
    // Get all settings for a group
    public function getGroup(string $group): array
    
    // Check if setting exists in database (vs env fallback)
    public function isOverridden(string $group, string $key): bool
    
    // Reset to env default
    public function reset(string $group, string $key): void
}
```

### 4.2 Config Integration

Update config files to use SettingService with env fallback:

```php
// config/mail.php (before)
'host' => env('MAIL_HOST', 'smtp.mailgun.org'),

// config/mail.php (after)
'host' => app(SettingService::class)->get('mail', 'host', env('MAIL_HOST', 'smtp.mailgun.org')),
```

---

## 5. Caching Strategy

### 5.1 Cache Implementation

- Cache all settings on first access
- Cache key: `settings:{group}` or `settings:all`
- Cache TTL: 1 hour (configurable)
- Invalidate cache on any setting update

### 5.2 Cache Warming

- Warm cache on application boot (optional)
- Warm cache after setting updates

---

## 6. Security Considerations

### 6.1 Encryption

- API keys and secrets stored encrypted using `APP_KEY`
- Decrypt only when accessed
- Never log decrypted values

### 6.2 Access Control

- Only admin users can modify settings
- Audit log for all changes
- Some settings may require re-authentication

### 6.3 Sensitive Field Masking

- API keys shown as `sk-****...****` in UI
- Full value only shown on explicit reveal
- Copy button without revealing

---

## 7. Migration Strategy

### 7.1 Phased Approach

1. **Phase 1**: Create infrastructure (table, service, caching)
2. **Phase 2**: Migrate mail settings (simplest, good test case)
3. **Phase 3**: Migrate notification settings
4. **Phase 4**: Migrate LLM settings
5. **Phase 5**: Migrate SSO settings
6. **Phase 6**: Migrate backup settings

### 7.2 Backward Compatibility

- All settings default to env values if not in database
- Existing deployments continue working without changes
- Database settings override env when present

---

## 8. UI Components

Each settings category needs:
- Form with appropriate inputs (text, password, select, toggle)
- Test/validate button where applicable
- Reset to default button
- Save with immediate effect (no restart)

**Existing UI to extend**:
- Mail settings: `/configuration/email`
- Notification settings: `/configuration/notifications`
- LLM settings: `/configuration/llm`
- SSO settings: `/configuration/sso`
- Backup settings: `/configuration/backup`

---

## 9. Implementation Priority

| Feature | Priority | Effort | Value | Order |
|---------|----------|--------|-------|-------|
| Settings table + service | HIGH | Medium | High | 1 |
| Mail settings migration | HIGH | Low | High | 2 |
| Notification settings migration | HIGH | Medium | High | 3 |
| LLM settings migration | HIGH | Medium | High | 4 |
| Settings caching | MEDIUM | Low | Medium | 5 |
| SSO settings migration | MEDIUM | Medium | Medium | 6 |
| Backup settings migration | LOW | Medium | Low | 7 |
| Audit logging | LOW | Low | Low | 8 |

---

## 10. Files Reference

**Backend** (to be created/modified):
- `backend/database/migrations/xxxx_create_settings_table.php`
- `backend/app/Models/Setting.php`
- `backend/app/Services/SettingService.php`
- `backend/app/Http/Controllers/Api/SettingsController.php` (extend existing)
- `backend/config/*.php` - Update to use SettingService

**Frontend** (to be modified):
- Existing settings pages to use new API endpoints
- Add reset-to-default functionality

---

## 11. Related Roadmaps

- [Database Options Roadmap](database-options-roadmap.md) - Database connection settings
- [Integration Settings Roadmap](integration-settings-roadmap.md) - Related settings UI work
- [Admin Features Roadmap](admin-features-roadmap.md) - Admin-only settings access
