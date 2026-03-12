# "Get Cooking" Setup Wizard

**Date**: 2026-02-14  
**Type**: Feature (Developer Experience)

## Summary

Created a tiered "Get cooking" setup wizard that guides new users through customizing Sourdough for their own project. The wizard is triggered by saying "Get cooking" and walks through three tiers of questions, executing changes after each tier.

## Architecture

The wizard uses a **trigger-phrase Cursor rule** (`.cursor/rules/get-cooking.mdc`) that orchestrates a conversational flow across three tiers, each backed by a dedicated execution recipe.

### Tier Structure

| Tier | Focus | Recipe |
|------|-------|--------|
| 1 | Identity & Branding | `docs/ai/recipes/setup-identity-branding.md` |
| 2 | Features & Auth | `docs/ai/recipes/setup-features-auth.md` |
| 3 | Infrastructure & Repo | `docs/ai/recipes/setup-infrastructure-repo.md` |

Each tier asks questions first, executes changes, then offers a pause point before continuing. Users can stop after any tier and resume later.

### Tier 1: Identity & Branding

The most comprehensive tier. Covers renaming every "Sourdough" reference across ~50+ files:
- App name, short name, description
- Font pairing (7 presets)
- Brand color
- Environment files, frontend config, backend config, Docker files, notification channels, documentation

### Tier 2: Features & Auth

Subtractive customization:
- Feature removal checklist (AI/LLM, notification channels, backup destinations, PWA, search, HIPAA logging)
- Auth model selection (email/pass, +SSO, +2FA, +Passkeys) with cascading file deletions
- Per-feature file lists for clean removal

### Tier 3: Infrastructure & Repo

Runtime configuration:
- Database choice (SQLite/MySQL/PostgreSQL) with docker-compose service templates
- Dev port, timezone, mail from address
- Git history reset and remote setup
- Final verification checklist

## Files Created

| File | Purpose |
|------|---------|
| `.cursor/rules/get-cooking.mdc` | Trigger-phrase rule orchestrating the wizard |
| `docs/ai/recipes/setup-identity-branding.md` | Tier 1 execution recipe (~50+ file rename manifest) |
| `docs/ai/recipes/setup-features-auth.md` | Tier 2 execution recipe (feature removal + auth config) |
| `docs/ai/recipes/setup-infrastructure-repo.md` | Tier 3 execution recipe (database, port, git) |

## Files Modified

| File | Change |
|------|--------|
| `docs/ai/recipes/setup-new-project.md` | Refactored from monolithic recipe to master index linking to 3 tier recipes |
| `.cursor/rules/new-project-setup.mdc` | Updated to reference "Get cooking" trigger and tiered recipes |
| `docs/ai/context-loading.md` | Added 3 tier recipes to New Project Setup task type |
| `docs/ai/README.md` | Updated context loading table and recipes table with tier recipes |
| `FORK-ME.md` | Updated AI callout to mention "Get cooking" trigger phrase |

## User Onboarding Flow

The wizard now starts with a **Welcome & Orientation** step before any tier questions:

1. **Wizard Overview** — Shows a table of all 3 tiers with what each covers, explains pause/resume
2. **Quick Tips** — Presented immediately so the user knows the key shortcuts (push, roadmap, recipes) from the start
3. **Confirmation** — Waits for the user to say "ready" before proceeding to Tier 1

After all three tiers complete, the wizard presents:

- **Quick Tips Recap** — Brief reminder of push, roadmap, and recipe shortcuts (not the full tables again)
- **Key Documentation** — Table of important doc files and what they contain
- **Working with Roadmaps** — How to add features to the roadmap (`docs/roadmaps.md`), work from the roadmap, complete items, and maintain the plan/journal/ADR structure

### Why Quick Tips are shown upfront (not just at the end)

Previously, Quick Tips were only shown after completing all 3 tiers. This meant users who stopped early (or whose conversation ended mid-wizard) never saw them. Since the tips explain core workflow shortcuts (push, roadmaps, recipes) that users benefit from knowing immediately, they are now presented as part of the initial welcome before Tier 1 begins.

## Design Decisions

- **Three tiers instead of one big recipe**: Lets users pause and resume, reduces cognitive overload, makes each recipe focused and testable
- **Execute after each tier**: Changes are applied incrementally rather than all at once, giving users confidence at each step
- **Resume detection**: The rule checks existing state (app name, feature presence, git history) to determine which tier to resume from
- **Comprehensive rename manifest**: Tier 1 lists every single file that contains "Sourdough" references, organized by category, ensuring nothing is missed
- **Welcome-first onboarding**: Quick Tips and wizard outline are shown before Tier 1, not just after Tier 3, ensuring users always see them regardless of how far they get
