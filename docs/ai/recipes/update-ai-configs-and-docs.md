# Recipe: Update AI Configs & Documentation (Tier 4)

Finish the project setup by updating AI assistant configurations, help center text, and documentation to match the new app identity. This tier is optional — the app is fully functional after Tier 3.

**When to use:** After completing Tier 3 from the "Get cooking" wizard, or standalone when AI assistant configs and docs still reference "Sourdough."

**Context to read first:**
```
.sourdough-setup.json          # Has the app name and answers from previous tiers
frontend/lib/help/help-content.ts
README.md
CLAUDE.md
```

**Inputs needed (from Tier 1 answers in `.sourdough-setup.json`):**
- `APP_NAME` — Full app name
- `APP_SLUG` — Lowercase kebab-case (e.g., "acme-dashboard")
- `APP_DESCRIPTION` — One-line description

**Step tracking keys** (append to `steps_completed` in `.sourdough-setup.json` as each step finishes):
`"ai_configs"` → `"help_text"` → `"documentation"` → `"github_guidance"` → `"final_check"`

---

## Step 1: Update AI Assistant Configurations

These config files tell AI tools (Cursor, Copilot, Windsurf) about the project. They still reference "Sourdough" after Tier 1 since Tier 1 doesn't modify them.

### `CLAUDE.md`

Update the project name in the first heading:
```markdown
# <APP_NAME>
```

Update any project-specific guidance that refers to "Sourdough" by name (keep all structural guidance, patterns, and commands — just replace the app name references).

### `.cursor/rules/*.mdc` files

Search all Cursor rule files for "Sourdough" references that describe the project (not generic recipe instructions). Update:
- `get-cooking.mdc` — search for any occurrences where "Sourdough" is used as the current project name (not as template origin credit)
- Any other `.mdc` files in `.cursor/rules/` that have project-specific descriptions

**Do not modify:** The structural instructions, tier descriptions, or references to Sourdough as the *template origin* (e.g., "based on Sourdough", "forked from Sourdough" can stay).

### `.github/copilot-instructions.md` (if exists)

If this file exists, update any references to "Sourdough" as the current project name. Replace with `<APP_NAME>`.

### `.windsurfrules` (if exists)

If this file exists, update project name references in the same way.

**Write to state file:** After completing Step 1, append `"ai_configs"` to `steps_completed` in the tier 4 entry of `.sourdough-setup.json`.

---

## Step 2: Update Help Center Welcome Text

The help center welcome article still says "Welcome to Sourdough." Update it to use the new app name.

### `frontend/lib/help/help-content.ts`

Find the welcome article (in the `getting-started` category) and update:
1. The article title — replace "Sourdough" with `<APP_NAME>`
2. The article content — replace all "Sourdough" references with `<APP_NAME>`
3. Add a Sourdough credit line at the end of the welcome content (optional, recommended):
   ```
   *<APP_NAME> is built on [Sourdough](https://github.com/Sourdough-start/sourdough), an open-source Laravel + Next.js starter.*
   ```

### `backend/config/search-pages.php`

Update the help page title for the welcome article:
```php
'title' => 'Help: Welcome to <APP_NAME>',
```

**Write to state file:** After completing Step 2, append `"help_text"` to `steps_completed`.

---

## Step 3: Update Documentation

### `README.md`

Rewrite the header section:
```markdown
# <APP_NAME>

<APP_DESCRIPTION>

Built on [Sourdough](https://github.com/your-org/sourdough) — a Laravel 11 + Next.js 16 starter.
```

Keep any technical documentation sections that are still accurate. Remove or update any sections that still describe Sourdough's generic features as if they're the project's own.

### `CHANGELOG.md`

If the CHANGELOG still shows Sourdough release history, replace it with a fresh start:
```markdown
# Changelog

## [Unreleased]

## [0.1.0] — <YYYY-MM-DD>

- Initial project setup based on Sourdough
```

### `VERSION`

Confirm this is already `0.1.0` (Tier 1 should have reset it). If not, reset it:
```
0.1.0
```

### `docs/overview.md` (if exists)

Update the project title and description to use `<APP_NAME>` and `<APP_DESCRIPTION>`.

### `docs/api/openapi.yaml` (if exists)

Update the API title and description:
```yaml
info:
  title: <APP_NAME> API
  description: |
    <APP_DESCRIPTION>
  contact:
    name: <APP_NAME> Support
```

**Write to state file:** After completing Step 3, append `"documentation"` to `steps_completed`.

---

## Step 4: GitHub Repository Guidance

The wizard cannot update GitHub directly, but guide the user on what to do manually.

Tell the user:
> *"One last thing — your GitHub repository may still show 'Sourdough' in the description and topics. Here's how to update it:*
> 1. *Go to your repository on GitHub*
> 2. *Click the gear icon next to "About"*
> 3. *Update the Description to:* `<APP_DESCRIPTION>`
> 4. *Update the Website if you have a domain*
> 5. *Add relevant topics (remove any Sourdough-specific tags)*"

**Write to state file:** After completing Step 4, append `"github_guidance"` to `steps_completed`.

---

## Step 5: Final Check — No Remaining Sourdough References

Run a search to catch any remaining "Sourdough" references used as the *project name* (not as template origin credit):

```bash
# Check frontend configs and help text
grep -r "Sourdough" frontend/config/ frontend/lib/ frontend/public/ --include="*.ts" --include="*.tsx" --include="*.js" --include="*.json" --include="*.html" 2>/dev/null | grep -v "node_modules"

# Check backend configs
grep -r "Sourdough" backend/config/ --include="*.php" 2>/dev/null

# Check AI configs and docs
grep -r "Sourdough" CLAUDE.md README.md .cursor/rules/ 2>/dev/null
```

Review any hits. References to "Sourdough" as the template origin/credit are fine to keep. References using it as the current app name should be updated.

**Write to state file:** After completing Step 5, append `"final_check"` to `steps_completed`.

---

## Completion

After all steps complete, update `.sourdough-setup.json` to mark Tier 4 as `complete`.

Tell the user:
> *"Your project is now fully customized! Here's what was updated:*
> - ✅ AI assistant configurations (Cursor, Copilot, Windsurf)
> - ✅ Help center welcome text
> - ✅ README and documentation
> - ✅ Version reset to 0.1.0
>
> *You're ready to start development. Just say 'push' to make your first commit, or describe what you want to build and I'll find the right recipe."*

---

## Checklist

- [ ] CLAUDE.md updated with new app name
- [ ] Cursor rules updated (project name references only)
- [ ] Copilot instructions updated (if file exists)
- [ ] Windsurf rules updated (if file exists)
- [ ] Help welcome article updated with new app name + optional Sourdough credit
- [ ] `backend/config/search-pages.php` help title updated
- [ ] README.md rewritten with new name, description, and Sourdough credit
- [ ] CHANGELOG.md reset (or confirmed already reset by Tier 1)
- [ ] VERSION confirmed as 0.1.0
- [ ] docs/overview.md updated (if exists)
- [ ] OpenAPI spec title updated (if exists)
- [ ] GitHub repo metadata guidance given
- [ ] Final grep check — no project-name "Sourdough" references remain
- [ ] `.sourdough-setup.json` tier 4 marked `complete`

---

## Files Modified by This Recipe

| Category | Files | What Changes |
|----------|-------|-------------|
| AI configs | `CLAUDE.md`, `.cursor/rules/*.mdc`, `.github/copilot-instructions.md`, `.windsurfrules` | Project name references |
| Help content | `frontend/lib/help/help-content.ts` | Welcome article text |
| Help search | `backend/config/search-pages.php` | Welcome article title |
| Documentation | `README.md`, `CHANGELOG.md`, `VERSION`, `docs/overview.md`, `docs/api/openapi.yaml` | Project name, description, reset |
| Setup state | `.sourdough-setup.json` | Tier 4 marked complete |

## Related

- [Setup New Project (master index)](setup-new-project.md)
- [Setup Identity & Branding (Tier 1)](setup-identity-branding.md)
- [Setup Features & Auth (Tier 2)](setup-features-auth.md)
- [Setup Infrastructure & Repo (Tier 3)](setup-infrastructure-repo.md)
