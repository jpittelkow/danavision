# LLM Settings Page Consolidation - 2026-01-29

## Overview

Consolidated the LLM System settings page (`/configuration/llm-system`) into the AI / LLM Settings page (`/configuration/ai`). The LLM System page had a runtime error (`ReferenceError: mode is not defined`) and overlapped with AI Settings (mode, providers). System-wide defaults (timeout, logging, council/aggregation options) are now an admin-only "System Defaults" card on the AI Settings page. The LLM System page and nav entry were removed.

## Implementation Approach

- **AI Settings page**: Added `useAuth`, admin check, and state for system defaults (timeout, logging_enabled, council_min_providers, council_strategy, aggregation_parallel, aggregation_include_sources). Fetch via `GET /llm-settings` and save via `PUT /llm-settings` when admin. New "System Defaults" card at bottom (admin-only) with timeout input, logging switch, and conditional council (min providers, strategy) and aggregation (parallel, include sources) sections keyed off the user's current orchestration mode.
- **Navigation**: Removed "LLM system" from configuration layout sidebar and `/configuration/llm-system` from page-title-manager. Removed unused `Cpu` icon import from layout.
- **Deletion**: Deleted `frontend/app/(dashboard)/configuration/llm-system/page.tsx` and the `llm-system` directory.
- **Backend**: No changes. `/llm-settings` remains the system-defaults API; AI Settings calls both `/llm/config` (user providers/mode) and `/llm-settings` (system defaults, admin-only).

## Challenges Encountered

- **SaveButton**: SaveButton is `type="submit"`; System Defaults needed a form wrapper with `onSubmit` calling `saveSystemDefaults` and `preventDefault`.
- **useEffect deps**: Wrapped `fetchAIConfig` and `fetchSystemDefaults` in `useCallback` and included them in effect dependency arrays to satisfy exhaustive-deps.

## Observations

- Council and aggregation sections in System Defaults reuse the user's current mode from the Orchestration Mode tabs, so admins see relevant options without a separate mode selector for system defaults.
- Mode and primary provider from the old LLM System page were intentionally not carried over; AI Settings already handles mode per-user, and system defaults for those can use env.

## Trade-offs

- System Defaults are only visible to admins; configuration layout already restricts to admins, so all viewers of the AI page are admins today. The check remains for clarity and future use.

## Next Steps (Future Considerations)

- Ensure LLM orchestrator and backend consume system defaults (timeout, logging, council/aggregation) from `SettingService` where applicable.
- Update roadmaps and collapsible-settings / llm-model-discovery plans that reference `/configuration/llm-system`.

## Testing Notes

- As admin: open `/configuration/ai`; confirm System Defaults card appears with timeout, logging, and mode-specific sections when mode is council or aggregation.
- Change system defaults, save; confirm success toast and `GET /llm-settings` returns updated values.
- Confirm "LLM system" nav item and `/configuration/llm-system` route are removed; direct navigation to `/configuration/llm-system` yields 404.
