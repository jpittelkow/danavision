# Recipe: Setup Features & Auth (Tier 2)

Remove unwanted features and configure the authentication model. This tier handles subtractive customization — the template ships with everything, and the user prunes what they don't need.

**When to use:** After completing Tier 1 (identity & branding) from the "Get cooking" wizard. Can also be run standalone for feature trimming.

**Context to read first:**
```
docs/customization-checklist.md            # Detailed file lists for each feature
frontend/app/(dashboard)/configuration/layout.tsx  # Nav items to update
backend/routes/api.php                     # Routes to clean up
```

**Inputs needed:**
- Features to remove (AI/LLM, Payments/Stripe, notification channels, backup destinations, PWA, search, HIPAA)
- Auth model choice (email/password only, +SSO, +2FA, +Passkeys)
- SSO providers to keep (if SSO selected)

---

## Step 1: Remove AI/LLM Integration

If the user chose to remove AI/LLM:

**Delete these files/directories:**
- `backend/app/Services/LLM/` (entire directory)
- `backend/config/llm.php`
- `backend/app/Http/Controllers/Api/LLMController.php`
- `backend/app/Http/Controllers/Api/LLMModelController.php`
- `backend/app/Services/LLMModelDiscoveryService.php`
- `frontend/app/(dashboard)/configuration/ai/` (entire directory)

**Edit these files:**
- `backend/routes/api.php` — Remove all LLM-related route groups (search for "llm" or "LLM")
- `backend/database/migrations/` — Remove LLM-related migrations (search for "llm" in filenames)
- `frontend/app/(dashboard)/configuration/layout.tsx` — Remove the AI nav item from `navigationGroups`

**Clean up env files:**
- Remove from `backend/.env.example`: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, `OLLAMA_BASE_URL`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AZURE_OPENAI_*`
- Remove from `.env.example` (root): Any AI-related env vars if present

---

## Step 2: Remove Payments / Stripe Integration

If the user chose to remove payments:

**Delete these files/directories:**
- `backend/app/Services/Stripe/` (entire directory)
- `backend/config/stripe.php`
- `backend/app/Http/Controllers/Api/StripeCustomerController.php`
- `backend/app/Http/Controllers/Api/StripeWebhookController.php`
- `backend/app/Http/Controllers/Api/PaymentController.php`
- `backend/app/Http/Controllers/Api/PaymentWebhookController.php`
- `backend/app/Models/Payment.php`
- `backend/app/Models/StripeCustomer.php`
- `backend/app/Models/StripeWebhookEvent.php`
- `frontend/lib/stripe.ts`
- `frontend/app/(dashboard)/configuration/stripe/` (entire directory, if exists)
- `frontend/app/(dashboard)/configuration/payments/` (entire directory, if exists)

**Edit these files:**
- `backend/routes/api.php` — Remove payment and Stripe route groups
- `backend/database/migrations/` — Delete migration files containing "stripe" or "payment" in the filename
- `backend/.env.example` — Remove: `STRIPE_PUBLIC_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`
- `frontend/app/(dashboard)/configuration/layout.tsx` — Remove Stripe and Payments nav items

---

## Step 3: Remove Notification Channels (Selective)

For each channel the user wants to remove, delete the corresponding file from `backend/app/Services/Notifications/Channels/`:

| Channel | File to Delete | Env Vars to Remove |
|---------|---------------|-------------------|
| Telegram | `TelegramChannel.php` | `TELEGRAM_BOT_TOKEN` |
| Discord | `DiscordChannel.php` | `DISCORD_WEBHOOK_URL` |
| Slack | `SlackChannel.php` | `SLACK_WEBHOOK_URL` |
| Signal | `SignalChannel.php` | `SIGNAL_CLI_PATH`, `SIGNAL_PHONE_NUMBER` |
| SMS (Twilio) | `TwilioChannel.php` | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` |
| SMS (Vonage) | `VonageChannel.php` | `VONAGE_API_KEY`, `VONAGE_API_SECRET`, `VONAGE_FROM` |
| SMS (SNS) | `SNSChannel.php` | (uses AWS keys) |
| Matrix | `MatrixChannel.php` | (configured in app) |
| ntfy | `NtfyChannel.php` | `NTFY_ENABLED`, `NTFY_SERVER` |
| Web Push | `WebPushChannel.php` | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` |
| FCM | `FCMChannel.php` | `FCM_SERVER_KEY` |

**Always keep:**
- `DatabaseChannel.php` — In-app notifications (core feature)
- `EmailChannel.php` — Email notifications (almost always needed)

**Edit these files:**
- `backend/config/notifications.php` — Remove entries for deleted channels
- `backend/.env.example` — Remove env vars listed above
- `frontend/app/(dashboard)/configuration/notifications/page.tsx` — Remove UI sections for deleted channels (if the page has channel-specific configuration sections)

---

## Step 4: Remove Backup Destinations (Selective)

For each destination the user wants to remove, delete from `backend/app/Services/Backup/Destinations/`:

| Destination | File to Delete |
|------------|---------------|
| S3/AWS | `S3Destination.php` |
| SFTP | `SFTPDestination.php` |
| Google Drive | `GoogleDriveDestination.php` |

**Always keep:**
- `LocalDestination.php` — Local backups (always useful)

**Edit these files:**
- `backend/config/settings-schema.php` — Remove settings for deleted destinations from the backup group
- `backend/.env.example` — Remove `AWS_BUCKET`, `AWS_ENDPOINT` if S3 removed

---

## Step 5: Remove PWA

If the user chose to remove PWA:

**Delete these files:**
- `frontend/public/sw.js`
- `frontend/public/offline.html`
- `frontend/app/api/manifest/route.ts`
- `frontend/lib/use-install-prompt.ts`
- `frontend/lib/request-queue.ts`
- `frontend/lib/web-push.ts`
- `frontend/components/install-prompt.tsx`
- `frontend/components/service-worker-setup.tsx`
- `frontend/lib/service-worker.ts`
- `frontend/public/workbox/` (entire directory if exists)
- `scripts/generate-pwa-icons.mjs` (if exists)

**Edit these files:**
- `frontend/components/app-shell.tsx` — Remove PWA-related imports and components (`InstallPrompt`, `ServiceWorkerSetup`, `OfflineIndicator`)
- `frontend/app/layout.tsx` — Remove manifest link from metadata (the `manifest: "/api/manifest"` line)
- `frontend/app/(dashboard)/user/preferences/page.tsx` — Remove "Install App" section if present

**Note:** Keep `frontend/public/manifest.json` as a minimal static fallback if desired, or delete it too.

---

## Step 6: Remove Meilisearch / Full-Text Search

If the user chose to remove search:

**1. Delete backend search code:**
- `backend/app/Services/Search/` (entire directory)
- `backend/app/Http/Controllers/Api/SearchController.php`
- `backend/app/Http/Controllers/Api/Admin/SearchAdminController.php`
- `backend/app/Console/Commands/SearchReindexCommand.php`
- `backend/config/search-pages.php`

**2. Delete frontend search code:**
- `frontend/components/search/` (entire directory)
- `frontend/lib/search.ts`
- `frontend/lib/search-pages.ts`
- `frontend/app/(dashboard)/configuration/search/` (entire directory, if exists)

**3. Edit backend routes:**
- `backend/routes/api.php` — Remove all route groups containing "search" or "SearchController":
  - Search for `Route::prefix('search')->group(...)` and delete the block
  - Search for `Route::prefix('admin/search')->group(...)` and delete the block

**4. Edit Docker/Supervisor configuration:**
- `docker/supervisord.conf` — Remove the Meilisearch program block. Find `[program:meilisearch]` and delete from that line to the next `[program:...]` section.

**5. Edit docker-compose files:**
- `docker-compose.yml`:
  - Remove the `meilisearch:` service block entirely
  - Remove `meilisearch_data` volume from the volumes section at the bottom
  - Remove these env vars from the `app` service environment: `MEILISEARCH_HOST`, `MEILI_ENV`, `MEILI_MASTER_KEY`, `SCOUT_DRIVER`
- `docker-compose.prod.yml` — Same changes as above

**6. Edit backend environment:**
- `backend/.env.example` — Remove or comment out: `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILI_ENV`, `MEILI_MASTER_KEY`
- `.env.example` (root) — Remove `MEILI_MASTER_KEY` if present

**7. Edit frontend:**
- `frontend/app/(dashboard)/configuration/layout.tsx` — Remove the search nav item
- `frontend/components/app-shell.tsx` — Remove `<SearchProvider>` and its import

**8. Clean up Laravel Scout config (if exists):**
- `backend/config/scout.php` — Delete this file if it exists (or set `SCOUT_DRIVER=null` in env to disable without deleting)

**Verification — run after deletion:**
```bash
# Should return 0 results if search is fully removed
docker exec sourdough-dev bash -c "grep -r 'SearchController\|meilisearch\|SCOUT_DRIVER' backend/app --include='*.php' 2>/dev/null | wc -l"
# Should return 0 results
grep -r 'SearchProvider\|search-pages' frontend --include='*.ts' --include='*.tsx' 2>/dev/null | wc -l
```

---

## Step 7: Remove HIPAA / Access Logging

**Warning:** HIPAA access logging is woven into the core middleware pattern. Removing it is deeper surgery than other features. Make sure the user understands this.

If the user confirms removal:

**Delete these files:**
- `backend/app/Services/AccessLogService.php`
- `backend/app/Http/Middleware/LogResourceAccess.php`
- `backend/app/Models/AccessLog.php`
- `backend/app/Http/Controllers/Api/AccessLogController.php`
- `frontend/app/(dashboard)/configuration/access-logs/` (if exists)

**Edit these files:**
- `backend/routes/api.php` — Remove `log.access` middleware from all routes that use it, remove access-logs route group
- `backend/bootstrap/app.php` — Remove middleware alias registration for `log.access`
- `backend/database/migrations/` — Remove access_logs table migration
- `backend/.env.example` — Remove `ACCESS_LOG_RETENTION_DAYS`, `HIPAA_ACCESS_LOGGING_ENABLED`
- `frontend/app/(dashboard)/configuration/layout.tsx` — Remove access logs nav item
- `frontend/app/(dashboard)/configuration/log-retention/page.tsx` — Remove HIPAA toggle section (keep the rest of log retention if it has other settings)

---

## Step 8: Configure Auth Model

The auth model is **cumulative** — each tier adds to the previous. You can only remove whole tiers from the top down. The diagram below shows what is removed for each choice:

```
AUTH TIERS — CUMULATIVE (each builds on the previous)

┌────────────────────────────────────────────────────────┐
│ Option 1: Email/Password Only (Simplest)               │
│ • Email/password login only                            │
│ • Removals: SSO + 2FA + Passkeys (all three sets)     │
└────────────────────────────────────────────────────────┘
                          ↓ adds
┌────────────────────────────────────────────────────────┐
│ Option 2: + SSO (Default)                              │
│ • Email/password + OAuth login buttons                 │
│ • Configure: which providers to keep                   │
│ • Removals: 2FA + Passkeys                            │
└────────────────────────────────────────────────────────┘
                          ↓ adds
┌────────────────────────────────────────────────────────┐
│ Option 3: + 2FA (Enhanced Security)                    │
│ • Email/password + SSO + TOTP/recovery codes          │
│ • Removals: Passkeys only                             │
└────────────────────────────────────────────────────────┘
                          ↓ adds
┌────────────────────────────────────────────────────────┐
│ Option 4: Full Auth Stack (Keep Everything)            │
│ • Email/password + SSO + 2FA + WebAuthn Passkeys      │
│ • Removals: None                                      │
└────────────────────────────────────────────────────────┘
```

**Decision table — what gets deleted for each choice:**

| Choice | SSO | 2FA | Passkeys | File sets removed |
|--------|-----|-----|----------|-------------------|
| Email/pass only | ❌ | ❌ | ❌ | All three |
| + SSO (default) | ✅ | ❌ | ❌ | 2FA + Passkeys |
| + 2FA | ✅ | ✅ | ❌ | Passkeys only |
| Full stack | ✅ | ✅ | ✅ | None |

The auth model is tiered — each level adds to the previous:

### Email/Password Only (remove SSO, 2FA, and Passkeys)

**Remove SSO — delete these files:**
- `backend/app/Services/Auth/SSOService.php`
- `backend/app/Http/Controllers/Api/SSOController.php`
- `backend/app/Http/Controllers/Api/SSOSettingController.php`
- `backend/config/sso.php`
- `backend/database/migrations/*_create_social_accounts_table.php`
- `frontend/app/(dashboard)/configuration/sso/` (entire directory)
- `frontend/app/auth/callback/page.tsx`
- `frontend/components/auth/sso-buttons.tsx`
- `frontend/components/auth/auth-divider.tsx`
- `frontend/components/admin/sso-setup-modal.tsx`

**Edit these files for SSO removal:**
- `backend/routes/api.php` — Remove SSO route groups
- `backend/routes/web.php` — Remove SSO browser routes (redirect + callback)
- `backend/config/services.php` — Remove Socialite OAuth credential blocks
- `backend/config/settings-schema.php` — Remove `sso` group
- `backend/app/Providers/ConfigServiceProvider.php` — Remove `injectSSOConfig()` call
- `backend/.env.example` — Remove all SSO provider env vars (GOOGLE_*, GITHUB_*, MICROSOFT_*, APPLE_*, DISCORD_*, GITLAB_*, OIDC_*, SSO_*)
- `frontend/app/(auth)/login/page.tsx` — Remove SSO buttons section
- `frontend/app/(auth)/register/page.tsx` — Remove SSO buttons section
- `frontend/app/(dashboard)/configuration/layout.tsx` — Remove SSO nav item

**Remove 2FA — delete these files:**
- `backend/app/Services/Auth/TwoFactorService.php`
- `backend/app/Http/Controllers/Api/TwoFactorController.php`
- `backend/app/Http/Middleware/Ensure2FAVerified.php`
- `backend/app/Http/Middleware/Ensure2FASetupWhenRequired.php`
- `backend/tests/Unit/TwoFactorServiceTest.php` (if exists)
- `backend/tests/Feature/TwoFactorTest.php` (if exists)
- `frontend/components/auth/two-factor-form.tsx`

**Edit these files for 2FA removal:**
- `backend/routes/api.php` — Remove 2FA route group
- `backend/config/settings-schema.php` — Remove `two_factor_mode` from auth group
- `frontend/app/(dashboard)/user/security/page.tsx` — Remove 2FA setup/disable section
- `frontend/app/(auth)/login/page.tsx` — Remove 2FA verification step
- `frontend/app/(dashboard)/configuration/security/page.tsx` — Remove 2FA mode radio group

**Remove Passkeys — delete these files:**
- `backend/app/Services/Auth/PasskeyService.php`
- `backend/app/Http/Controllers/Api/PasskeyController.php`
- `backend/database/migrations/*_create_webauthn_credentials_table.php`
- `frontend/components/auth/passkey-register-dialog.tsx`
- `frontend/components/auth/passkey-login-button.tsx`
- `frontend/lib/use-passkeys.ts`

**Edit these files for Passkey removal:**
- `backend/routes/api.php` — Remove passkeys route group
- `backend/config/settings-schema.php` — Remove `passkey_mode` from auth group
- `frontend/app/(dashboard)/user/security/page.tsx` — Remove passkey management section
- `frontend/app/(auth)/login/page.tsx` — Remove passkey login button
- `frontend/app/(dashboard)/configuration/security/page.tsx` — Remove passkey mode radio group
- `docker-compose.yml` — Remove `AUTH_PASSKEY_MODE` env var

### Email/Password + SSO (remove 2FA and Passkeys)

Keep all SSO files. Remove 2FA and Passkey files as listed above.

**Follow-up:** Ask which SSO providers to pre-configure. For each provider NOT selected, remove its env vars from `backend/.env.example` (but keep the SSO infrastructure).

### Email/Password + SSO + 2FA (remove Passkeys only)

Keep SSO and 2FA files. Remove only Passkey files as listed above.

### Full Auth Stack (keep everything)

No removals needed. All auth features stay.

---

## Step 9: Update Configuration Navigation

After all removals, review `frontend/app/(dashboard)/configuration/layout.tsx` to ensure:

1. Nav items for deleted features are removed
2. Remaining nav items still work
3. No broken imports from deleted feature pages

Common nav items to check:
- AI / LLM Configuration
- SSO Settings
- Search Settings
- Access Logs
- Any feature-specific config pages

---

## Step 10: Clean Up Environment Files

Do a final pass on both `.env.example` files to:

1. Remove env vars for all deleted features
2. Remove commented-out blocks that reference deleted features
3. Ensure remaining env vars are accurate

---

## Step 11: Update Help Guides

When a feature is removed, the corresponding help article and search entry must also be removed to keep the help center accurate.

**Edit `frontend/lib/help/help-content.ts`** — Remove the article(s) for deleted features from the appropriate category.

**Edit `backend/config/search-pages.php`** — Remove the `help:` search entries for deleted help articles.

| Feature Removed | Help Articles to Remove | Search Entries to Remove |
|---|---|---|
| AI/LLM | `ai-llm-settings` | `help-ai-llm-settings` |
| Notification channels | Channel-specific articles if any | Corresponding search entries |
| Backup destinations | N/A (keep general backup article) | N/A |
| PWA | N/A (no dedicated help article) | N/A |
| Search | `search-config` | `help-search-config` |
| HIPAA logging | `access-logs` | `help-access-logs` |
| SSO | `sso-configuration` | `help-sso-configuration` |
| 2FA | Remove 2FA references from `two-factor` article | N/A |
| Passkeys | `passkeys` | `help-passkeys` |

Also remove any `HelpLink` components from deleted config pages (these are deleted with the page files).

---

## Step 12: Verify Feature Removal

After all deletions are complete, run these verification commands to confirm nothing was missed.

**LLM removal:**
```bash
docker exec sourdough-dev bash -c "grep -r 'LLMController\|LLMService\|llm' backend/app --include='*.php' 2>/dev/null | grep -v '^Binary' | wc -l"
# Should return 0 (or only false positives in comments)
```

**Stripe removal:**
```bash
docker exec sourdough-dev bash -c "grep -r 'StripeCustomer\|StripeWebhook\|PaymentController' backend/app --include='*.php' 2>/dev/null | wc -l"
# Should return 0
```

**Search removal:**
```bash
docker exec sourdough-dev bash -c "grep -r 'SearchController\|meilisearch\|SCOUT_DRIVER' backend/app --include='*.php' 2>/dev/null | wc -l"
grep -r 'SearchProvider\|search-pages' frontend --include='*.ts' --include='*.tsx' 2>/dev/null | wc -l
# Both should return 0
```

**PWA removal:**
```bash
grep -r 'ServiceWorkerSetup\|InstallPrompt' frontend --include='*.tsx' 2>/dev/null | wc -l
# Should return 0
```

**Auth features:**
- If SSO removed: check `frontend/app/(auth)/login/page.tsx` has no SSO buttons section
- If 2FA removed: check `frontend/app/(dashboard)/user/security/page.tsx` has no 2FA setup section
- If Passkeys removed: check login page has no passkey button

**General lint check:**
```bash
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npm run lint 2>&1 | grep -i 'cannot find\|Module not found'"
# Should return empty (no broken imports)
```

If any check finds unexpected results, review the corresponding step above and complete any missed edits.

---

## Checklist

- [ ] AI/LLM removed (if chosen) — files, routes, migrations, nav item, env vars
- [ ] Payments/Stripe removed (if chosen) — services, controllers, models, routes, migrations, env vars
- [ ] Notification channels trimmed — channel files, config, env vars
- [ ] Backup destinations trimmed — destination files, settings schema, env vars
- [ ] PWA removed (if chosen) — SW, manifest route, install prompt, web push, app-shell cleanup
- [ ] Search removed (if chosen) — search service, controllers, frontend components, supervisord, env vars, volumes
- [ ] HIPAA logging removed (if chosen) — service, middleware, model, controller, routes, migrations
- [ ] Auth model configured — SSO/2FA/Passkeys removed per choice
- [ ] Configuration navigation updated — no broken nav items
- [ ] Environment files cleaned — no orphaned env vars
- [ ] No broken imports — deleted file references cleaned up
- [ ] Routes cleaned — no routes pointing to deleted controllers
- [ ] Help articles removed/updated for deleted features
- [ ] Search entries (search-pages.php) removed for deleted help articles

---

## Files Modified by This Recipe

The exact files depend on which features are removed. See each step above for specific file lists.

| Category | Typical Changes |
|----------|----------------|
| Backend controllers | Delete controllers for removed features |
| Backend services | Delete service classes for removed features |
| Backend config | Remove settings groups, channel configs |
| Backend routes | Remove route groups for removed features |
| Backend migrations | Remove tables for removed features |
| Frontend pages | Delete config pages for removed features |
| Frontend components | Delete feature-specific components |
| Frontend lib | Delete hooks/utilities for removed features |
| Docker | Remove Meilisearch, env vars, volumes |
| Environment | Remove env vars for removed features |
| Config nav | Remove nav items for removed features |

## Related

- [Setup New Project (master index)](setup-new-project.md)
- [Setup Identity & Branding (Tier 1)](setup-identity-branding.md)
- [Setup Infrastructure & Repo (Tier 3)](setup-infrastructure-repo.md)
- [Customization Checklist](../customization-checklist.md) — Detailed file lists for each feature

### Implementation Journal

- [Configurable Auth Features (2026-01-30)](../../journal/2026-01-30-configurable-auth-features.md)
- [Get Cooking Setup Wizard (2026-02-14)](../../journal/2026-02-14-get-cooking-setup-wizard.md)
