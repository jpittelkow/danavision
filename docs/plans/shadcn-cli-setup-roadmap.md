# shadcn/ui CLI Setup Roadmap

**Priority**: Low (Technical Improvement)
**Status**: Done (2026-01-27)

## Overview

Refactor from manually-copied shadcn/ui components to native CLI-managed setup. This enables easier component updates, automatic dependency management, and standardized configuration.

## Current State (post-migration)

- `frontend/components.json` configures shadcn CLI (style: default, rsc, tsx, tailwind)
- 16 components in `frontend/components/ui/`; 8 now use Radix primitives (dialog, select, dropdown-menu, tabs, switch, avatar, label, separator)
- Radix deps: slot, avatar, dialog, dropdown-menu, label, select, separator, switch, tabs
- `tailwindcss-animate` and `--popover` / `--popover-foreground` CSS variables added for select/dropdown
- `npx shadcn@latest add <component>` works from `frontend/`

## Target State (achieved)

- `components.json` configuration file for shadcn CLI
- All required Radix UI dependencies properly installed
- Ability to use `npx shadcn@latest add <component>` for new components
- Existing interactive components replaced with standard Radix-based shadcn/ui versions

## Implementation Steps

### Phase 1: Setup CLI Configuration

1. Create `frontend/components.json` with project settings
2. Verify `frontend/lib/utils.ts` has the `cn()` helper
3. Verify Tailwind config has shadcn/ui theme variables

### Phase 2: Audit Dependencies

1. Check each component for required Radix dependencies
2. Install missing `@radix-ui/*` packages
3. Verify all components still work after dependency updates

### Phase 3: Verify Components

1. Compare existing components against latest shadcn/ui versions
2. Note any customizations that should be preserved
3. Update components if needed (preserving customizations)

### Phase 4: Documentation

1. Update `docs/ai/anti-patterns.md` with correct CLI usage
2. Update `docs/ai/patterns.md` if import patterns change
3. Add to `docs/quick-reference.md` how to add new components

## Components to Audit

| Component | Radix Dependency | Status |
|-----------|------------------|--------|
| alert | - | No Radix |
| avatar | @radix-ui/react-avatar | ✓ Migrated |
| badge | - | No Radix |
| button | @radix-ui/react-slot | ✓ Unchanged |
| card | - | No Radix |
| dialog | @radix-ui/react-dialog | ✓ Migrated |
| dropdown-menu | @radix-ui/react-dropdown-menu | ✓ Migrated |
| input | - | No Radix |
| label | @radix-ui/react-label | ✓ Migrated |
| select | @radix-ui/react-select | ✓ Migrated |
| separator | @radix-ui/react-separator | ✓ Migrated |
| skeleton | - | No Radix |
| switch | @radix-ui/react-switch | ✓ Migrated |
| table | - | No Radix |
| tabs | @radix-ui/react-tabs | ✓ Migrated |
| textarea | - | No Radix |

## Risks & Considerations

- **Low Risk**: Existing components work; this is an improvement, not a fix
- **Customizations**: Some components may have project-specific changes
- **Breaking Changes**: Newer shadcn/ui versions might have different APIs

## Success Criteria

- [x] `components.json` exists and is valid
- [x] `npx shadcn@latest add` works for adding new components
- [x] All existing components still function correctly
- [x] Documentation updated with CLI workflow (`docs/quick-reference.md`, `docs/ai/anti-patterns.md`)
