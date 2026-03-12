# Architecture Connection Map

How data flows through the application and where components connect.

## High-Level Request Flow

```
Browser
   │
   ▼
Nginx (port 80, single entry point)
   │
   ├─► /* (non-API routes)
   │         │
   │         ▼
   │   Next.js (frontend, port 3000)
   │         │
   │         ▼
   │   HTML / static assets
   │
   └─► /api/* requests
          │
          ▼
       PHP-FPM (Laravel, port 9000)
          │
          ▼
       Controller → Service → Model → Database
          │
          ▼
       JSON Response
```

**Note:** Nginx is always the entry point. The browser never hits Next.js or PHP-FPM directly. Nginx routes based on path: `/api/*` goes to PHP-FPM, everything else goes to Next.js.

## Frontend ↔ Backend Connection Points

| Frontend Location | API Route | Backend Controller | Service |
|-------------------|-----------|-------------------|---------|
| `lib/auth.ts` | `/api/auth/*` | `AuthController` | `AuthService`, `SSOService`, `TwoFactorService` |
| `lib/api.ts` (generic) | `/api/*` | Various | Various |
| Settings pages | `/api/settings/*` | `SettingController` | - |
| Notifications UI | `/api/notifications/*` | `NotificationController` | `NotificationOrchestrator` |
| Dashboard | `/api/dashboard/*` | `DashboardController` | - |
| Backup admin | `/api/backup/*` | `BackupController` | `BackupService` |
| LLM features | `/api/llm/*` | `LLMController` | `LLMOrchestrator` |

## Service Layer Architecture

### Notifications

```
Trigger (code, event, schedule)
        │
        ▼
NotificationOrchestrator
        │
        ├─► Channel Selection (based on config/user preferences)
        │
        ▼
   ┌────┴────┬────────┬──────────┬─────────┐
   ▼         ▼        ▼          ▼         ▼
Database  Email   Telegram   Discord   [Others]
Channel   Channel  Channel    Channel   Channels
```

**Key Files:**
- Orchestrator: `backend/app/Services/Notifications/NotificationOrchestrator.php`
- Channels: `backend/app/Services/Notifications/Channels/`
- Config: `backend/config/notifications.php`
- Model: `backend/app/Models/Notification.php`
- Controller: `backend/app/Http/Controllers/Api/NotificationController.php`

### LLM Orchestration

```
Request (prompt, context)
        │
        ▼
  LLMOrchestrator
        │
        ├─► Mode Selection
        │   ├── single: One provider
        │   ├── aggregation: All respond, primary synthesizes
        │   └── council: All providers "vote"
        │
        ▼
   ┌────┴────┬────────┬──────────┐
   ▼         ▼        ▼          ▼
OpenAI   Anthropic  Ollama   [Others]
Provider  Provider  Provider  Providers
```

**Key Files:**
- Orchestrator: `backend/app/Services/LLM/LLMOrchestrator.php`
- Providers: `backend/app/Services/LLM/Providers/`
- Config: `backend/config/llm.php`
- Controller: `backend/app/Http/Controllers/Api/LLMController.php`
- ADR: `docs/adr/006-llm-orchestration-modes.md`

### Authentication

```
Login Request
     │
     ▼
AuthController
     │
     ├─► Standard Login (email/password)
     │         │
     │         ▼
     │   AuthService.attempt()
     │         │
     │         ├─► 2FA Required? → TwoFactorService.verify()
     │         │
     │         ▼
     │   Sanctum Session Cookie
     │
     └─► SSO Login (Google, GitHub, etc.)
               │
               ▼
         SSOService.redirect() / .callback()
               │
               ▼
         Create/Link User → Sanctum Session
```

**Key Files:**
- Controller: `backend/app/Http/Controllers/Api/AuthController.php`
- Services: `backend/app/Services/Auth/`
  - `AuthService.php`
  - `SSOService.php`
  - `TwoFactorService.php`
- Frontend: `frontend/lib/auth.ts`
- ADRs: `docs/adr/002-authentication-architecture.md`, `003-sso-provider-integration.md`, `004-two-factor-authentication.md`

### Settings Storage

```
Frontend Form
     │
     ▼
API Request (PUT /api/settings/{group})
     │
     ▼
SettingController
     │
     ├─► User Settings (user_id populated)
     │         │
     │         ▼
     │   settings table (group, key, value, user_id)
     │
     └─► System Settings (admin only, via SettingService)
               │
               ▼
         system_settings table (global, no user_id)
```

**Key Files:**
- Controller: `backend/app/Http/Controllers/Api/SettingController.php`
- Model: `backend/app/Models/Setting.php`
- Frontend pages: `frontend/app/(dashboard)/configuration/`

### Backup System

```
Trigger (manual, scheduled)
        │
        ▼
  BackupService
        │
        ├─► Create backup archive (database, files)
        │
        ▼
   Destination Selection
        │
   ┌────┴────┬────────┬──────────┐
   ▼         ▼        ▼          ▼
 Local      S3      SFTP     [Others]
  Dest     Dest     Dest     Destinations
```

**Key Files:**
- Service: `backend/app/Services/Backup/BackupService.php`
- Destinations: `backend/app/Services/Backup/Destinations/`
- Controller: `backend/app/Http/Controllers/Api/BackupController.php`
- ADR: `docs/adr/007-backup-system-design.md`

## Database Schema Relationships

```
users
  │
  ├──< settings (user_id)
  │
  ├──< notifications (user_id)
  │
  ├──< webhooks (user_id)
  │
  └──< [other user-scoped tables]

system_settings (no user_id - global)

backups (system-wide, admin-managed)
```

## Frontend Component Hierarchy

```
app/
├── (auth)/              # Unauthenticated routes
│   ├── login/
│   ├── register/
│   └── forgot-password/
│
└── (dashboard)/         # Authenticated routes (layout wraps all)
    ├── layout.tsx       # Sidebar + Header + Auth check
    ├── dashboard/       # Main dashboard
    ├── configuration/   # Admin configuration (grouped nav)
    │   └── layout.tsx   # Configuration sidebar nav
    ├── user/            # User pages (profile, security, preferences)
    ├── notifications/   # User notifications
    └── [feature]/       # Feature pages
```

## Configuration Files Map

| Purpose | Backend | Frontend |
|---------|---------|----------|
| App config | `backend/config/app.php` | `frontend/next.config.js` |
| Auth | `backend/config/auth.php`, `sanctum.php` | `frontend/lib/auth.ts` |
| Database | `backend/config/database.php` | - |
| Notifications | `backend/config/notifications.php` | - |
| LLM | `backend/config/llm.php` | - |
| Mail | `backend/config/mail.php` | - |
| Environment | `backend/.env` | `frontend/.env.local` |

## Middleware Chain

```
API Request
     │
     ▼
Laravel Middleware Stack:
  1. HandleCors
  2. ThrottleRequests
  3. auth:sanctum (if protected route)
  4. [Custom middleware]
     │
     ▼
Controller
```

**Route groups in `backend/routes/api.php`:**
- Public routes (no auth)
- `auth:sanctum` protected routes
- Admin-only routes (auth + admin check)
