# Recipe: Setup Infrastructure & Repository (Tier 3)

Configure the database, development port, timezone, mail, and git repository. This is the final tier — after this, the project is ready for first boot.

**When to use:** After completing Tier 2 (features & auth) from the "Get cooking" wizard. Can also be run standalone for infrastructure changes.

**Context to read first:**
```
docker-compose.yml
docker-compose.prod.yml
docker/entrypoint.sh
backend/.env.example
backend/config/app.php
backend/config/database.php
```

**Inputs needed:**
- `DATABASE` — sqlite (default), mysql, or pgsql
- `DEV_PORT` — Development port (default: 8080)
- `DEPLOYMENT_TARGET` — Docker local (default), cloud VPS, or NAS (Unraid/Synology)
- `TIMEZONE` — Timezone string (default: UTC)
- `MAIL_FROM` — Mail from address (or skip)
- `REPO_URL` — GitHub repo URL (or "later")
- `RESET_GIT` — Whether to reset git history (default: yes)

---

## Step 1: Configure Database

### SQLite (default) — No changes needed

SQLite is the default. No modifications required.

### MySQL

**Update `backend/.env.example`:**
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=<APP_SLUG>
DB_USERNAME=<APP_SLUG>
DB_PASSWORD=changeme
```

Uncomment the MySQL block and comment out the SQLite block.

**Add a `db` service to `docker-compose.yml`:**
```yaml
  db:
    image: mysql:8
    container_name: ${CONTAINER_NAME:-<APP_SLUG>}-mysql
    environment:
      - MYSQL_ROOT_PASSWORD=secret
      - MYSQL_DATABASE=<APP_SLUG>
      - MYSQL_USER=<APP_SLUG>
      - MYSQL_PASSWORD=secret
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"
    restart: unless-stopped
```

**Update the `app` service in `docker-compose.yml`:**
```yaml
    environment:
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=<APP_SLUG>
      - DB_USERNAME=<APP_SLUG>
      - DB_PASSWORD=changeme
    depends_on:
      - db
```

Remove or comment out the SQLite env vars (`DB_CONNECTION=sqlite`, `DB_DATABASE=...sqlite`).

**Add the volume:**
```yaml
volumes:
  mysql_data:
    driver: local
```

**Update `docker-compose.prod.yml`** with the same db service and env var changes.

**Update `docker/entrypoint.sh`:**
Gate the SQLite-specific file creation logic (creating the .sqlite file, setting permissions) behind a check:
```bash
if [ "$DB_CONNECTION" = "sqlite" ]; then
  # existing SQLite setup logic
fi
```

### PostgreSQL

Same pattern as MySQL but with PostgreSQL specifics:

**Update `backend/.env.example`:**
```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=<APP_SLUG>
DB_USERNAME=postgres
DB_PASSWORD=changeme
```

**Add a `db` service to `docker-compose.yml`:**
```yaml
  db:
    image: postgres:16
    container_name: ${CONTAINER_NAME:-<APP_SLUG>}-postgres
    environment:
      - POSTGRES_DB=<APP_SLUG>
      - POSTGRES_USER=postgres
      - POSTGRES_PASSWORD=secret
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    restart: unless-stopped
```

Update the `app` service similarly to MySQL (with pgsql driver and port 5432).

**Note:** The Dockerfile already installs `pdo_mysql`, `pdo_pgsql`, and `pdo_sqlite`. No Dockerfile changes needed.

---

## Step 2: Set Development Port

If the user chose a port other than 8080:

**Update `.env.example` (root):**
```env
APP_PORT=<DEV_PORT>
```

**Verify `docker-compose.yml`** uses `${APP_PORT:-<DEV_PORT>}:80` (it should already use the env var).

---

## Step 3: Set Timezone

If the user chose a timezone other than UTC:

**Update `backend/.env.example`:**
```env
APP_TIMEZONE=<TIMEZONE>
```

**Update `backend/config/app.php`** default:
```php
'timezone' => env('APP_TIMEZONE', '<TIMEZONE>'),
```

---

## Step 4: Set Mail From Address

If the user provided a mail from address:

**Update `backend/.env.example`:**
```env
MAIL_FROM_ADDRESS="<MAIL_FROM>"
```

---

## Step 5: Deployment Target Configuration

### Docker Local (default)

No additional changes needed.

### Cloud VPS

Add a note about reverse proxy configuration:

**Update `.env.example` (root):**
```env
# Set to your public-facing URL in production
APP_URL=https://yourdomain.com

# Trust all proxies (or set specific IPs)
TRUSTED_PROXIES=*
```

### NAS (Unraid/Synology)

**Update `.env.example` (root) with PUID/PGID guidance:**
```env
# Unraid default: PUID=99 PGID=100 (nobody:users)
# Synology default: PUID=1000 PGID=1000
PUID=
PGID=
```

Ensure the PUID/PGID section is prominent and well-documented.

---

## Step 6: Repository Setup

### Reset Git History (recommended for new projects)

```bash
rm -rf .git
git init --initial-branch=main
git add .
git commit -m "Initial commit based on Sourdough"
```

**Note:** `--initial-branch=main` requires git 2.28+. If this flag fails, use `git init` then `git checkout -b main` to rename the default branch.

**Note on PowerShell:** These commands work in PowerShell. The `rm -rf` equivalent is `Remove-Item -Recurse -Force .git` but `rm -rf` works in modern PowerShell too.

### Important for Windows Users

If you're on Windows, configure git line endings before your first commit to prevent CRLF/LF issues:

```bash
git config core.autocrlf true
```

Verify it's set:
```bash
git config core.autocrlf
# Should output: true
```

### Keep Sourdough History

If the user wants to keep the commit history, just update the remote:

```bash
# If a remote already exists (e.g., pointing to Sourdough)
git remote set-url origin <REPO_URL>

# If no remote exists yet
git remote add origin <REPO_URL>
```

Then push:
```bash
git branch --show-current   # Confirm branch name
git push -u origin main     # or master, depending on the output above
```

### Set Remote After Fresh Init (if URL provided)

If git history was reset with `git init`, add the remote and push:

```bash
git remote add origin <REPO_URL>
git branch --show-current   # Confirm branch name
git push -u origin main     # or master, depending on the output above
```

### Update CI/CD

Review `.github/workflows/` files:
- Update Docker image references if using a container registry
- Update any repository-specific URLs
- Ensure workflow triggers match the new branch structure

---

## Step 7: Back Up the APP_KEY

After the first successful boot, the Docker entrypoint auto-generates an `APP_KEY` and saves it to `${DATA_DIR}/.app_key`. This key encrypts all sensitive model attributes (AI provider API keys, webhook secrets) and backup settings.

**Tell the user:**

> Your `APP_KEY` has been auto-generated and saved to the data volume. **Back up this key now** — if the data volume is lost, all encrypted data (API keys, webhook secrets) becomes unrecoverable.
>
> To retrieve the key:
> ```bash
> docker exec <container-name> cat /data/.app_key
> ```
>
> Store this value in a password manager or secure location outside the container. For production deployments, you can also set `APP_KEY` as an environment variable in your docker-compose file or orchestrator — the entrypoint will use the provided value instead of generating a new one.

---

## Step 8: Final Verification

This is the most important step. Run through this checklist to make sure everything works.

**Optional:** If `scripts/verify-setup.sh` exists, run it for an automated pre-check:
```bash
bash scripts/verify-setup.sh
```
This checks app name, fonts, database config, Docker, and git. Fix any failures before proceeding.

### 8a: Rebuild and Start

```bash
docker-compose down
docker-compose up -d --build
```

Wait for the container to be healthy (check with `docker-compose ps`).

### 8b: Create First Account

1. Open http://localhost:<DEV_PORT> in a browser
2. Register a new account (the first user automatically becomes admin)
3. Verify you can log in

### 8c: Verify Identity & Branding (Tier 1)

- [ ] App name shows correctly in the browser title bar
- [ ] App name shows correctly on the login/register pages
- [ ] App name shows correctly in the sidebar (expanded and collapsed)
- [ ] Fonts render correctly — headings should use the heading font, body text uses body font
- [ ] Brand color appears in the mobile status bar / address bar (if testing on mobile or DevTools device mode)

### 8d: Verify Feature Removal (Tier 2)

- [ ] Removed features are gone from the Configuration navigation
- [ ] No broken links or 404 pages when navigating the app
- [ ] Remaining features load and function correctly
- [ ] Auth features work as configured (SSO buttons present/absent, 2FA option available/hidden)
- [ ] No console errors in browser DevTools

### 8e: Verify Infrastructure (Tier 3)

- [ ] Database connection works (data persists after restart)
- [ ] If MySQL/PostgreSQL: the db container is running and healthy
- [ ] If search was kept: Meilisearch is running (check Configuration > Search)
- [ ] Port is correct (app accessible at the configured port)

### 8f: Set Runtime Branding

After first boot, go to **Configuration > Branding** to:
1. Set the brand color (if different from the default set in code)
2. Upload a logo (if available)
3. Upload a dark mode logo variant (if available)
4. Upload a favicon (if available)

---

## Checklist

- [ ] Database configured (env vars, docker-compose service if MySQL/PG, entrypoint gated)
- [ ] Dev port set (if non-default)
- [ ] Timezone set (if non-UTC)
- [ ] Mail from address set (if provided)
- [ ] Deployment target configured (proxy, PUID/PGID notes)
- [ ] Git history reset or remote updated
- [ ] CI/CD workflows reviewed
- [ ] APP_KEY backed up from data volume
- [ ] Docker rebuilt and started
- [ ] First account created
- [ ] App name verified in UI
- [ ] Fonts verified
- [ ] Removed features confirmed gone
- [ ] No console errors
- [ ] Runtime branding set (colors, logo)

---

## Files Modified by This Recipe

| Category | Files | What Changes |
|----------|-------|-------------|
| Environment | `.env.example`, `backend/.env.example` | DB vars, port, timezone, mail |
| Docker compose | `docker-compose.yml`, `docker-compose.prod.yml` | DB service, port, env vars, volumes |
| Docker entrypoint | `docker/entrypoint.sh` | SQLite logic gating (if MySQL/PG) |
| Backend config | `backend/config/app.php` | Timezone default |
| CI/CD | `.github/workflows/*` | Image refs, repo URLs |
| Git | `.git/` | Reset or remote update |

## Related

- [Setup New Project (master index)](setup-new-project.md)
- [Setup Identity & Branding (Tier 1)](setup-identity-branding.md)
- [Setup Features & Auth (Tier 2)](setup-features-auth.md)
- [Customization Checklist](../customization-checklist.md)
