# Get Cooking Script Audit Roadmap

Re-audit the "Get Cooking" onboarding script against the current codebase. The previous audit (2026-03-02) scored 8/10 with 16 issues across 9 sections. This follow-up verifies prior fixes, identifies new drift from recent features, and tests the full flow end-to-end.

**Priority**: MEDIUM
**Status**: NOT STARTED
**Last Updated**: 2026-03-09

**Previous Audit**: See memory files `get-cooking-audit.md`, `get-cooking-fixes.md`, `tier-4-proposal.md`

---

## Overview

The "Get Cooking" system is a 3-tier setup wizard for onboarding new projects forked from Sourdough:
- **Tier 1 (Identity & Branding)** — Renames app identity across ~100+ files
- **Tier 2 (Features & Auth)** — Removes unwanted features, configures auth
- **Tier 3 (Infrastructure & Repository)** — Database, git, deployment setup

Key files:
- `FORK-ME.md` (entry point)
- `docs/ai/recipes/setup-new-project.md` (master index)
- `docs/ai/recipes/setup-identity-branding.md` (Tier 1)
- `docs/ai/recipes/setup-features-auth.md` (Tier 2)
- `docs/ai/recipes/setup-infrastructure-repo.md` (Tier 3)
- `.cursor/rules/get-cooking.mdc` (Cursor rule)

---

## Phase 1: Verify Previous Fixes

Check whether the 16 issues from the 2026-03-02 audit have been addressed.

- [ ] **Issue #1: Incomplete Tier 1 file list** — File count claim (~50+) vs reality (~100+)
- [ ] **Issue #2: Auth dependency tree** — Invalid SSO/2FA/Passkey combinations not guarded
- [ ] **Issue #3: Search/Meilisearch cleanup** — Broken references and unused Docker volumes after removal
- [ ] **Issue #4: No pre-flight validation** — Docker/git not running leads to unclear errors
- [ ] **Issue #5: Geist font npm install** — Missing install step would cause build failures
- [ ] **Issue #6: Stripe not in feature removal checklist** — Incomplete customization options
- [ ] **Issue #7-16: Remaining issues** — Review all items from `get-cooking-fixes.md`

## Phase 2: Identify New Drift

Check for features added since the last audit that the script doesn't account for.

- [ ] **New controllers/services** — Cross-reference new backend services against Tier 2 feature removal list
- [ ] **New frontend pages** — Verify any new config pages or dashboard sections are covered
- [ ] **New settings** — Check if new `settings-schema.php` or `user-settings-schema.php` entries need Tier 1 handling
- [ ] **New ADRs** — Verify new ADRs are referenced where relevant in setup recipes
- [ ] **New dependencies** — Check if new npm/composer packages need removal steps in Tier 2
- [ ] **Search registration** — Verify new search-pages entries are covered in cleanup steps
- [ ] **Real-time/webhooks/usage tracking** — New features (ADR-027/028/029) may need removal options

## Phase 3: End-to-End Test

Run the complete onboarding flow to verify it works.

- [ ] **Fork and run Tier 1** — Execute identity/branding rename with a test app name, verify no broken references
- [ ] **Run Tier 2** — Remove at least one feature (e.g., LLM, Stripe), verify clean removal
- [ ] **Run Tier 3** — Complete infrastructure setup, verify Docker build succeeds
- [ ] **Build verification** — Confirm the customized app builds and runs without errors
- [ ] **Test auth combinations** — Verify valid auth configurations (e.g., local-only, SSO+2FA, passkeys)

## Phase 4: Update & Improve

Apply fixes found during the audit.

- [ ] **Update file lists** — Ensure all tier recipes reference current file paths
- [ ] **Add missing removal steps** — Add steps for any new features not covered
- [ ] **Add pre-flight checks** — Script should validate Docker, git, Node are available before starting
- [ ] **Update Cursor rule** — Ensure `.cursor/rules/get-cooking.mdc` matches current wizard flow
- [ ] **Consider Tier 4** — Evaluate the tier-4 proposal (automating AI configs & documentation updates)

---

## Success Criteria

- All 16 previous issues either fixed or documented as won't-fix with rationale
- Script covers all features added since last audit
- Full end-to-end run produces a working, buildable app
- No broken file references in any tier recipe
