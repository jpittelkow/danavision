# shadcn/ui CLI Migration - January 27, 2026

## Overview

We migrated from custom, manually-copied shadcn-style components to a CLI-managed setup using standard Radix-based shadcn/ui components. This removes technical debt, improves accessibility, and enables `npx shadcn@latest add <component>` for future additions.

## Implementation Approach

### Phase 1: CLI Setup and Dependencies

- Created `frontend/components.json` with style default, rsc, tsx, tailwind paths, and `@/components` / `@/lib/utils` aliases.
- Installed Radix packages: avatar, dialog, dropdown-menu, label, select, separator, switch, tabs (slot was already present).
- Installed `tailwindcss-animate` and added it to `tailwind.config.ts` for `animate-in`, `fade-in-0`, etc.
- Added `--popover` and `--popover-foreground` CSS variables to `app/globals.css` (light and dark) and `popover` colors to Tailwind config, required by the new Select and DropdownMenu components.

### Phase 2: Component Replacement

Replaced 8 custom implementations with standard shadcn/ui versions via:

```bash
npx shadcn@latest add dialog select dropdown-menu tabs switch avatar label separator --overwrite --yes
```

**Replaced components:** dialog, select, dropdown-menu, tabs, switch, avatar, label, separator. Each now uses the corresponding `@radix-ui/*` primitive. Button, alert, badge, card, input, skeleton, table, and textarea were left unchanged (button already used Radix Slot; others are pure CSS).

### Phase 3: Verification and Documentation

- `npm run build` succeeds; types and lint checks pass.
- Dev server starts successfully.
- Updated `docs/quick-reference.md` with shadcn CLI commands and "Add shadcn component" in the lookup table.
- Updated `docs/ai/anti-patterns.md` to emphasize using the CLI from `frontend/` and `components.json`.
- Updated `docs/plans/shadcn-cli-setup-roadmap.md`: status Done, success criteria checked, component audit table updated.

## Challenges Encountered

- **Popover variables:** New Select and DropdownMenu use `bg-popover` / `text-popover-foreground`. These were missing; we added them to globals.css and Tailwind.
- **Animations:** Components use `animate-in`, `fade-in-0`, etc. from `tailwindcss-animate`. The project didn’t use this plugin; we added it to avoid broken or missing animations.

## Observations

- Custom dialog, select, and dropdown implementations used React Context and native DOM. The Radix-based replacements preserve the same composition API (`Dialog` + `DialogTrigger` + `DialogContent`, etc.) so existing usage did not require code changes.
- `DropdownMenuContent align="end"` is still used in theme-toggle and user-table; Radix supports `align` and forwards it correctly.
- Pre-existing unit test failures (api, auth, Button size) are unchanged and unrelated to this migration.

## Trade-offs

- **Bundle size:** Additional Radix packages add roughly 20–30KB gzipped. Accepted for better accessibility and maintainability.
- **tailwindcss-animate:** New dependency. Required for component animations; no simpler option identified.

## Next Steps (Future Considerations)

- Add other shadcn components (e.g. tooltip, sheet) via `npx shadcn@latest add <name>` as needed.
- Consider running `npx shadcn@latest add <component> --overwrite` periodically to pull upstream fixes and improvements.

## Testing Notes

- Run `npm run build` and `npm run dev` to verify.
- Manually test theme toggle (dropdown), settings pages (selects, tabs, switches), user table (dropdown, dialogs), and profile (avatar). E2E and unit tests were not extended for this migration.
