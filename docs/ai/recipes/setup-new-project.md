# Recipe: Setup New Project from Sourdough

Master guide for initializing a new project using Sourdough as the starting point. The setup is broken into **tiers** so you can stop at any boundary and continue later. Progress is tracked in `.sourdough-setup.json` at the project root so you can reliably resume where you left off.

**When to use:** The user wants to fork/clone Sourdough and start a new application. They say things like "build me an app," "set up a new project," "I'm starting from Sourdough," "customize this for my project," or **"Get cooking"** (trigger phrase).

**Trigger phrase:** Say **"Get cooking"** to start the wizard. The Cursor rule at `.cursor/rules/get-cooking.mdc` orchestrates the conversation.

**Context to read first:**
```
FORK-ME.md
docs/customization-checklist.md
frontend/config/app.ts
frontend/config/fonts.ts
.env.example
```

---

## How It Works

```
┌─────────────────────────────────────────────────────────┐
│  "Get cooking"                                          │
│                                                         │
│  Step 0: Pre-Flight Validation                          │
│  ├── Check Docker, git, Node are available             │
│  └── Read .sourdough-setup.json (resume if exists)     │
│                                                         │
│  Step 1: Welcome & Orient                               │
│  ├── Show wizard outline (all tiers, what each does)   │
│  ├── Present Quick Tips (push, roadmap, recipes)        │
│  └── Wait for user to confirm "Ready for Tier 1?"       │
│                                                         │
│  Tier 1: Identity & Branding                            │
│  ├── Ask: name, short name, description, color, fonts   │
│  ├── Write .sourdough-setup.json (tier 1 in_progress)  │
│  ├── Execute: setup-identity-branding.md                │
│  ├── Update state: tier 1 complete                      │
│  └── Result: App is renamed, fonts set, docs reset      │
│                                                         │
│  Tier 2: Features & Auth                                │
│  ├── Ask: which features to keep, auth model, SSO       │
│  ├── Update .sourdough-setup.json (tier 2 in_progress) │
│  ├── Execute: setup-features-auth.md                    │
│  ├── Update state: tier 2 complete                      │
│  └── Result: Features removed, auth trimmed, help synced│
│                                                         │
│  Tier 3: Infrastructure & Repository                    │
│  ├── Ask: database, port, timezone, mail, git           │
│  ├── Update .sourdough-setup.json (tier 3 in_progress) │
│  ├── Execute: setup-infrastructure-repo.md              │
│  ├── Update state: tier 3 complete                      │
│  └── Result: Ready for first boot                       │
│                                                         │
│  Tier 4: AI Configs & Documentation (Optional)          │
│  ├── Ask: update AI configs, help text, docs?           │
│  ├── Execute: update-ai-configs-and-docs.md             │
│  └── Result: Fully customized, no Sourdough refs remain │
│                                                         │
│  Verification: Rebuild, create account, test everything │
│  Recap: Quick Tips reminder + Key Docs + Roadmap guide  │
└─────────────────────────────────────────────────────────┘
```

The wizard starts with a **Pre-Flight Validation** and **Welcome & Orientation** before proceeding to tiers. Each tier writes its answers and status to `.sourdough-setup.json` so resumption is deterministic — no guessing based on file state.

Each tier asks questions first, then executes immediately before moving to the next. At each boundary, the user can stop and resume later.

---

## Tier 1: Identity & Branding

**Questions:**

| Question | Why | Default |
|----------|-----|---------|
| App name? | Title bar, emails, auth pages, PWA manifest, everywhere | Sourdough |
| Short name? (1-3 chars) | Collapsed sidebar, PWA icon text | SD |
| One-line description? | README, meta tags, PWA manifest | — |
| Primary brand color? | Buttons, links, accents, mobile status bar | `#3b82f6` (blue) |
| Font style preference? | Body + heading fonts throughout the app | Inter + Newsreader |
| Logo file, or text for now? | Header, sidebar, auth pages, favicon | Text fallback |

**Font pairing suggestions:**

| Style | Body Font | Heading Font |
|-------|-----------|--------------|
| Clean & modern (default) | Inter | Newsreader |
| Geometric & modern | DM Sans | DM Serif Display |
| Friendly & warm | Plus Jakarta Sans | Lora |
| Techy / developer | Geist Sans | Geist Mono |
| Corporate & classic | Source Sans 3 | Playfair Display |
| Creative & bold | Poppins | Abril Fatface |
| Soft & readable | Nunito | Merriweather |

**Execution recipe:** [setup-identity-branding.md](setup-identity-branding.md)

This is the most comprehensive tier — it renames every "Sourdough" reference across ~100+ files including env files, frontend configs, backend configs, Docker files, notification channels, and documentation.

---

## Tier 2: Features & Auth

**Questions:**

| Question | Options | Default |
|----------|---------|---------|
| Keep AI/LLM integration? | Keep / Remove | Keep |
| Keep Payments / Stripe? | Keep / Remove | Keep |
| Which notification channels? | Email, Telegram, Discord, Slack, SMS (Twilio/Vonage/SNS), Signal, Matrix, ntfy, Web Push/FCM, In-App | All |
| Keep backup/restore? | Keep (which destinations?) / Remove | Keep all |
| Keep PWA? | Keep / Remove | Keep |
| Keep full-text search? | Keep / Remove | Keep |
| Keep HIPAA/access logging? | Keep / Remove | Keep |
| Auth model? | Email/pass only, +SSO, +2FA, +Passkeys | Email/pass + optional SSO |
| Which SSO providers? | Google, GitHub, Microsoft, Apple, Discord, GitLab | None pre-configured |

**Execution recipe:** [setup-features-auth.md](setup-features-auth.md)

This tier handles subtractive customization — removing features and auth tiers the user doesn't need.

---

## Tier 3: Infrastructure & Repository

**Questions:**

| Question | Options | Default |
|----------|---------|---------|
| Database? | SQLite, MySQL, PostgreSQL | SQLite |
| Dev port? | Any port | 8080 |
| Target deployment? | Docker local, cloud VPS, NAS | Docker local |
| Timezone? | Any timezone | UTC |
| Mail from address? | Email address / skip | Skip |
| GitHub repo URL? | URL / later | Later |
| Reset git history? | Fresh start / Keep history | Fresh start |

**Execution recipe:** [setup-infrastructure-repo.md](setup-infrastructure-repo.md)

This tier configures the runtime environment and prepares the repository for development.

---

## Tier 4: AI Configs & Documentation (Optional)

After Tier 3, the app is fully functional. Tier 4 is an optional finishing step that ensures AI assistant tools and documentation are updated for the new project identity.

**Questions:**

| Question | Default |
|----------|---------|
| Update AI configs? (Cursor rules, Copilot, Windsurf) | Yes |
| Update help/welcome text? | Yes |
| Update README, CHANGELOG, and docs? | Yes |
| Add Sourdough credit in documentation? | Yes |

**Execution recipe:** [update-ai-configs-and-docs.md](update-ai-configs-and-docs.md)

If the user skips Tier 4, their choice is recorded in `.sourdough-setup.json` and they won't be prompted again on resume.

---

## Quick Reference: All Recipes

| Tier | Recipe | What It Does |
|------|--------|-------------|
| 1 | [setup-identity-branding.md](setup-identity-branding.md) | Renames app, sets fonts/color, resets docs |
| 2 | [setup-features-auth.md](setup-features-auth.md) | Removes features, configures auth model, trims help guides |
| 3 | [setup-infrastructure-repo.md](setup-infrastructure-repo.md) | Sets database, port, timezone, git |
| 4 *(optional)* | [update-ai-configs-and-docs.md](update-ai-configs-and-docs.md) | Updates AI tool configs, help text, README |

## Related

- [Customization Checklist](../customization-checklist.md) — Detailed feature removal file lists
- [FORK-ME.md](../../FORK-ME.md) — Overview of what Sourdough provides
- [Branding Roadmap](../plans/branding-ui-consistency-roadmap.md) — How branding/colors work
- [Get Cooking Rule](../../.cursor/rules/get-cooking.mdc) — Trigger phrase rule
- [Tier 4 Recipe](update-ai-configs-and-docs.md) — AI configs & documentation update

### Implementation Journal

- [Get Cooking Setup Wizard (2026-02-14)](../../journal/2026-02-14-get-cooking-setup-wizard.md)
