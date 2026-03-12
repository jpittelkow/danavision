# Compliance Templates - 2026-02-05

## Overview
Created template compliance documents for SOC 2 Type II, ISO 27001, and general security policies. Templates use placeholders (e.g., `[COMPANY_NAME]`, `[CONTACT_EMAIL]`) for users to customize when forking Sourdough for their own projects.

## Implementation Approach
- **Structure**: `docs/compliance/` with subdirectories `soc2/` and `iso27001/`
- **Template standards**: Header placeholders, Customize callouts, Sourdough feature references where applicable, revision history tables
- **Cross-references**: Policy documents link to each other and to Sourdough features (audit logs, RBAC, backup, SAST)

## Documents Created
- Core policies: security-policy, incident-response-plan, business-continuity-plan, sdlc-policy, data-handling-policy, access-control-policy
- SOC 2: README, security-controls (CC1–CC9), availability-controls (A1), privacy-controls (P1)
- ISO 27001: README, isms-framework (policy, risk assessment, SoA)
- Index: docs/compliance/README.md with usage guide and placeholder reference

## Observations
- Templates intentionally avoid Sourdough-specific branding in policy content so users can adopt as their own
- Sourdough feature references appear in "Customize" callouts and dedicated sections (e.g., SDLC references security-scan.sh)
- Roadmap updated to mark documentation tasks complete; several technical review tasks remain (access control review, encryption review, etc.)

## Testing Notes
- Verify all links resolve (compliance README to policies, policies to each other, roadmap to compliance)
- Placeholder consistency across documents
